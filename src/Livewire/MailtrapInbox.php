<?php

namespace Darvis\Mailtrap\Livewire;

use Darvis\Mailtrap\Models\MailLog;
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
        $this->selectedId = $id;
        $this->showDetail = true;
    }

    public function deleteLog(int $id): void
    {
        MailLog::whereKey($id)->delete();

        if ($this->selectedId === $id) {
            $this->reset('selectedId', 'showDetail');
        }

        $this->notice = 'Mail-log verwijderd.';
    }

    public function cleanup(): void
    {
        $days = (int) config('manta_mailtrap.logging.cleanup_after_days', 30);

        $deleted = MailLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->resetPage();

        $this->notice = "{$deleted} log(s) ouder dan {$days} dagen verwijderd.";
    }

    public function sendTest(): void
    {
        $this->validate([
            'testEmail' => ['required', 'email'],
        ]);

        try {
            Mail::raw(
                'Mailtrap testmail — verstuurd op '.now()->toDateTimeString().'.',
                function ($message): void {
                    $message->to($this->testEmail)
                        ->subject('Mailtrap testmail '.now()->format('H:i:s'));
                }
            );

            $this->testResult = [
                'ok' => true,
                'message' => "Verzonden via mailer '".config('mail.default')."'. Het resultaat verschijnt in de lijst hieronder.",
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
            $code === null => ['label' => 'In afwachting', 'color' => 'yellow'],
            (string) $code === '200' => ['label' => 'Verzonden', 'color' => 'green'],
            (int) $code === 550 => ['label' => 'Geblokkeerd', 'color' => 'red'],
            (int) $code === 400 => ['label' => 'Ongeldig', 'color' => 'orange'],
            default => ['label' => 'Mislukt ('.$code.')', 'color' => 'red'],
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
            'sent' => MailLog::where('status_code', '200')->count(),
            'pending' => MailLog::whereNull('status_code')->count(),
            'failed' => MailLog::whereNotNull('status_code')->where('status_code', '!=', '200')->count(),
        ];
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        $perPage = (int) config('manta_mailtrap.ui.per_page', 25);

        return MailLog::query()
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('recipient', 'like', $term)
                        ->orWhere('sender', 'like', $term)
                        ->orWhere('subject', 'like', $term);
                });
            })
            ->when($this->status === 'sent', fn ($q) => $q->where('status_code', '200'))
            ->when($this->status === 'pending', fn ($q) => $q->whereNull('status_code'))
            ->when($this->status === 'blocked', fn ($q) => $q->where('status_code', 550))
            ->when($this->status === 'failed', fn ($q) => $q->whereNotNull('status_code')->where('status_code', '!=', '200'))
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
        return view('mailtrap::livewire.inbox')
            ->layout(config('manta_mailtrap.ui.layout', 'components.layouts.app'));
    }
}
