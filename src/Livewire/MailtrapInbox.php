<?php

namespace Darvis\Mailtrap\Livewire;

use Darvis\Mailtrap\Http\Middleware\AuthorizeInbox;
use Darvis\Mailtrap\Models\MailLog;
use Darvis\Mailtrap\Support\MailtrapConfig;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Mailtrap inbox')]
class MailtrapInbox extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public ?int $selectedId = null;

    public bool $showDetail = false;

    public string $testEmail = '';

    /**
     * @var array{ok: bool, message: string}|null
     */
    public ?array $testResult = null;

    public ?string $notice = null;

    /**
     * Runs on the page load and on every update request. It also covers the
     * component when a host application embeds it outside the package route,
     * where the route middleware never runs.
     */
    public function boot(): void
    {
        AuthorizeInbox::authorize();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        AuthorizeInbox::authorize();

        $this->selectedId = $id;
        $this->showDetail = true;
    }

    public function deleteLog(int $id): void
    {
        AuthorizeInbox::authorize();

        MailLog::whereKey($id)->delete();

        if ($this->selectedId === $id) {
            $this->reset('selectedId', 'showDetail');
        }

        $this->notice = 'Mail log deleted.';
    }

    public function cleanup(): void
    {
        AuthorizeInbox::authorize();

        $days = MailtrapConfig::cleanupAfterDays();

        $deleted = (new MailLog)->prunable()->delete();

        $this->resetPage();

        $this->notice = "Deleted {$deleted} log(s) older than {$days} days.";
    }

    public function sendTest(): void
    {
        AuthorizeInbox::authorize();

        $this->validate([
            'testEmail' => ['required', 'email'],
        ]);

        try {
            Mail::raw(
                'Mailtrap test mail — sent at '.now()->toDateTimeString().'.',
                function ($message): void {
                    $message->to($this->testEmail)
                        ->subject('Mailtrap test mail '.now()->format('H:i:s'));
                }
            );

            $this->testResult = [
                'ok' => true,
                'message' => "Sent through mailer '".config('mail.default')."'. The result appears in the list below.",
            ];

            $this->resetPage();
        } catch (\Throwable $e) {
            $this->testResult = [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Map a stored status code to a human label and Flux badge color.
     *
     * @return array{label: string, color: string}
     */
    public function statusMeta(int|string|null $code): array
    {
        return match (true) {
            $code === null => ['label' => 'Pending', 'color' => 'yellow'],
            (string) $code === MailLog::STATUS_SENT => ['label' => 'Sent', 'color' => 'green'],
            (string) $code === MailLog::STATUS_BLOCKED => ['label' => 'Blocked', 'color' => 'red'],
            (int) $code === 400 => ['label' => 'Invalid', 'color' => 'orange'],
            default => ['label' => 'Failed ('.$code.')', 'color' => 'red'],
        };
    }

    /**
     * @return array{total: int, sent: int, pending: int, failed: int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => MailLog::count(),
            'sent' => MailLog::successful()->count(),
            'pending' => MailLog::pending()->count(),
            'failed' => MailLog::failed()->count(),
        ];
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $perPage = MailtrapConfig::uiPerPage();

        return MailLog::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('recipient', 'like', $term)
                        ->orWhere('sender', 'like', $term)
                        ->orWhere('subject', 'like', $term);
                });
            })
            ->when($this->status === 'sent', fn ($q) => $q->successful())
            ->when($this->status === 'pending', fn ($q) => $q->pending())
            ->when($this->status === 'blocked', fn ($q) => $q->blocked())
            ->when($this->status === 'failed', fn ($q) => $q->failed())
            ->latest('id')
            ->paginate($perPage);
    }

    #[Computed]
    public function selectedLog(): ?MailLog
    {
        return $this->selectedId !== null ? MailLog::find($this->selectedId) : null;
    }

    public function render()
    {
        AuthorizeInbox::authorize();

        return view('mailtrap::livewire.inbox')
            ->layout(MailtrapConfig::uiLayout());
    }
}
