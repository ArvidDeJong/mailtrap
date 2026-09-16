<div class="mx-auto max-w-7xl space-y-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Mailtrap inbox</flux:heading>
            <flux:subheading>Check that outgoing mail works — every send is logged here.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="$refresh" icon="arrow-path" variant="ghost">Refresh</flux:button>
            <flux:button
                wire:click="cleanup"
                wire:confirm="Delete all logs older than {{ (int) config('manta_mailtrap.logging.cleanup_after_days', 30) }} days?"
                icon="trash"
                variant="subtle"
            >Clean up</flux:button>
            <flux:modal.trigger name="send-test">
                <flux:button icon="paper-airplane" variant="primary">Send test mail</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" x-data x-init="setTimeout(() => $wire.set('notice', null), 4000)">
            {{ $notice }}
        </flux:callout>
    @endif

    {{-- Statistics --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <flux:card class="space-y-1">
            <flux:text size="sm">Total</flux:text>
            <flux:heading size="lg">{{ number_format($this->stats['total']) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">Sent</flux:text>
            <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ number_format($this->stats['sent']) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">Pending</flux:text>
            <flux:heading size="lg" class="text-yellow-600 dark:text-yellow-400">{{ number_format($this->stats['pending']) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">Failed</flux:text>
            <flux:heading size="lg" class="text-red-600 dark:text-red-400">{{ number_format($this->stats['failed']) }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.400ms="search"
            icon="magnifying-glass"
            placeholder="Search recipient, sender or subject…"
            class="sm:max-w-md"
        />
        <flux:select wire:model.live="status" class="sm:max-w-xs">
            <flux:select.option value="">All statuses</flux:select.option>
            <flux:select.option value="sent">Sent</flux:select.option>
            <flux:select.option value="pending">Pending</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
            <flux:select.option value="blocked">Blocked</flux:select.option>
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Recipient</flux:table.column>
            <flux:table.column>Subject</flux:table.column>
            <flux:table.column>Sender</flux:table.column>
            <flux:table.column>Time</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->logs as $log)
                @php($meta = $this->statusMeta($log->status_code))
                <flux:table.row wire:key="log-{{ $log->id }}" wire:click="select({{ $log->id }})" class="cursor-pointer">
                    <flux:table.cell class="py-2">
                        <flux:badge :color="$meta['color']" size="sm">{{ $meta['label'] }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $log->recipient }}</flux:table.cell>
                    <flux:table.cell class="max-w-xs truncate">{{ $log->subject ?: '—' }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $log->sender }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500">{{ $log->created_at?->format('d-m-Y H:i') }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">No mail found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Detail --}}
    <flux:modal name="log-detail" wire:model.self="showDetail" class="md:w-[32rem]">
        @if ($this->selectedLog)
            @php($meta = $this->statusMeta($this->selectedLog->status_code))
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="lg">Mail details</flux:heading>
                    <flux:badge :color="$meta['color']">{{ $meta['label'] }}</flux:badge>
                </div>

                <flux:separator />

                <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-zinc-500">Recipient</dt>
                    <dd class="col-span-2 font-medium">{{ $this->selectedLog->recipient }}</dd>

                    <dt class="text-zinc-500">Sender</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->sender }}</dd>

                    <dt class="text-zinc-500">Subject</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->subject ?: '—' }}</dd>

                    <dt class="text-zinc-500">Status</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->status_code ?? 'pending' }}</dd>

                    @if ($this->selectedLog->error_message)
                        <dt class="text-zinc-500">Error</dt>
                        <dd class="col-span-2 text-red-600 dark:text-red-400">{{ $this->selectedLog->error_message }}</dd>
                    @endif

                    @if ($this->selectedLog->type)
                        <dt class="text-zinc-500">Type</dt>
                        <dd class="col-span-2">{{ $this->selectedLog->type }}</dd>
                    @endif

                    @if ($this->selectedLog->model)
                        <dt class="text-zinc-500">Model</dt>
                        <dd class="col-span-2 break-all">{{ class_basename($this->selectedLog->model) }} #{{ $this->selectedLog->model_id }}</dd>
                    @endif

                    @if ($this->selectedLog->source_file)
                        <dt class="text-zinc-500">Source</dt>
                        <dd class="col-span-2 break-all font-mono text-xs">{{ $this->selectedLog->source_file }}:{{ $this->selectedLog->source_line }}</dd>
                    @endif

                    <dt class="text-zinc-500">Message-ID</dt>
                    <dd class="col-span-2 break-all font-mono text-xs">{{ $this->selectedLog->message_id }}</dd>

                    <dt class="text-zinc-500">Sent at</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->created_at?->format('d-m-Y H:i:s') }}</dd>
                </dl>

                <flux:separator />

                <div class="flex justify-between gap-2">
                    <flux:button
                        wire:click="deleteLog({{ $this->selectedLog->id }})"
                        wire:confirm="Delete this mail log?"
                        icon="trash"
                        variant="danger"
                    >Delete</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Send test mail --}}
    <flux:modal name="send-test" class="md:w-96">
        <form wire:submit="sendTest" class="space-y-4">
            <div>
                <flux:heading size="lg">Send test mail</flux:heading>
                <flux:subheading>Sends a mail through the active mailer ({{ config('mail.default') }}) and logs the result.</flux:subheading>
            </div>

            @if ($testResult)
                <flux:callout :variant="$testResult['ok'] ? 'success' : 'danger'" :icon="$testResult['ok'] ? 'check-circle' : 'exclamation-triangle'">
                    {{ $testResult['message'] }}
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>Email address</flux:label>
                <flux:input type="email" wire:model="testEmail" placeholder="name@example.com" />
                <flux:error name="testEmail" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Close</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    <span wire:loading.remove wire:target="sendTest">Send</span>
                    <span wire:loading wire:target="sendTest">Sending…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
