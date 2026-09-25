@use('Darvis\Mailtrap\Support\MailtrapConfig')

<div class="mx-auto max-w-7xl space-y-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('mailtrap::inbox.title') }}</flux:heading>
            <flux:subheading>{{ __('mailtrap::inbox.subtitle') }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="$refresh" icon="arrow-path" variant="ghost">{{ __('mailtrap::inbox.refresh') }}</flux:button>
            <flux:button
                wire:click="cleanup"
                wire:confirm="{{ __('mailtrap::inbox.cleanup_confirm', ['days' => MailtrapConfig::cleanupAfterDays()]) }}"
                icon="trash"
                variant="subtle"
            >{{ __('mailtrap::inbox.cleanup') }}</flux:button>
            <flux:modal.trigger name="send-test">
                <flux:button icon="paper-airplane" variant="primary">{{ __('mailtrap::inbox.send_test') }}</flux:button>
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
            <flux:text size="sm">{{ __('mailtrap::inbox.stats.total') }}</flux:text>
            <flux:heading size="lg">{{ number_format($this->stats['total']) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">{{ __('mailtrap::inbox.status.sent') }}</flux:text>
            <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ number_format($this->stats['sent']) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">{{ __('mailtrap::inbox.status.pending') }}</flux:text>
            <flux:heading size="lg" class="text-yellow-600 dark:text-yellow-400">{{ number_format($this->stats['pending']) }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">{{ __('mailtrap::inbox.status.failed') }}</flux:text>
            <flux:heading size="lg" class="text-red-600 dark:text-red-400">{{ number_format($this->stats['failed']) }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.400ms="search"
            icon="magnifying-glass"
            :placeholder="__('mailtrap::inbox.search')"
            class="sm:max-w-md"
        />
        <flux:select wire:model.live="status" class="sm:max-w-xs">
            <flux:select.option value="">{{ __('mailtrap::inbox.all_statuses') }}</flux:select.option>
            <flux:select.option value="sent">{{ __('mailtrap::inbox.status.sent') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('mailtrap::inbox.status.pending') }}</flux:select.option>
            <flux:select.option value="failed">{{ __('mailtrap::inbox.status.failed') }}</flux:select.option>
            <flux:select.option value="blocked">{{ __('mailtrap::inbox.status.blocked') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- Table --}}
    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>{{ __('mailtrap::inbox.columns.status') }}</flux:table.column>
            <flux:table.column>{{ __('mailtrap::inbox.columns.recipient') }}</flux:table.column>
            <flux:table.column>{{ __('mailtrap::inbox.columns.subject') }}</flux:table.column>
            <flux:table.column>{{ __('mailtrap::inbox.columns.sender') }}</flux:table.column>
            <flux:table.column>{{ __('mailtrap::inbox.columns.time') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->logs as $log)
                @php($meta = $this->statusMeta($log->status_code))
                <flux:table.row :wire:key="'log-'.$log->id" wire:click="select({{ $log->id }})" class="cursor-pointer">
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
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('mailtrap::inbox.empty') }}</flux:table.cell>
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
                    <flux:heading size="lg">{{ __('mailtrap::inbox.details') }}</flux:heading>
                    <flux:badge :color="$meta['color']">{{ $meta['label'] }}</flux:badge>
                </div>

                <flux:separator />

                <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-zinc-500">{{ __('mailtrap::inbox.columns.recipient') }}</dt>
                    <dd class="col-span-2 font-medium">{{ $this->selectedLog->recipient }}</dd>

                    <dt class="text-zinc-500">{{ __('mailtrap::inbox.columns.sender') }}</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->sender }}</dd>

                    <dt class="text-zinc-500">{{ __('mailtrap::inbox.columns.subject') }}</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->subject ?: '—' }}</dd>

                    <dt class="text-zinc-500">{{ __('mailtrap::inbox.columns.status') }}</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->status_code ?? 'pending' }}</dd>

                    @if ($this->selectedLog->error_message)
                        <dt class="text-zinc-500">{{ __('mailtrap::inbox.fields.error') }}</dt>
                        <dd class="col-span-2 text-red-600 dark:text-red-400">{{ $this->selectedLog->error_message }}</dd>
                    @endif

                    @if ($this->selectedLog->type)
                        <dt class="text-zinc-500">{{ __('mailtrap::inbox.fields.type') }}</dt>
                        <dd class="col-span-2">{{ $this->selectedLog->type }}</dd>
                    @endif

                    @if ($this->selectedLog->model)
                        <dt class="text-zinc-500">{{ __('mailtrap::inbox.fields.model') }}</dt>
                        <dd class="col-span-2 break-all">{{ class_basename($this->selectedLog->model) }} #{{ $this->selectedLog->model_id }}</dd>
                    @endif

                    @if ($this->selectedLog->source_file)
                        <dt class="text-zinc-500">{{ __('mailtrap::inbox.fields.source') }}</dt>
                        <dd class="col-span-2 break-all font-mono text-xs">{{ $this->selectedLog->source_file }}:{{ $this->selectedLog->source_line }}</dd>
                    @endif

                    <dt class="text-zinc-500">{{ __('mailtrap::inbox.fields.message_id') }}</dt>
                    <dd class="col-span-2 break-all font-mono text-xs">{{ $this->selectedLog->message_id }}</dd>

                    <dt class="text-zinc-500">{{ __('mailtrap::inbox.fields.sent_at') }}</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->created_at?->format('d-m-Y H:i:s') }}</dd>
                </dl>

                <flux:separator />

                <div class="flex justify-between gap-2">
                    <flux:button
                        wire:click="deleteLog({{ $this->selectedLog->id }})"
                        wire:confirm="{{ __('mailtrap::inbox.delete_confirm') }}"
                        icon="trash"
                        variant="danger"
                    >{{ __('mailtrap::inbox.delete') }}</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('mailtrap::inbox.close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Send test mail --}}
    <flux:modal name="send-test" class="md:w-96">
        <form wire:submit="sendTest" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('mailtrap::inbox.send_test') }}</flux:heading>
                <flux:subheading>{{ __('mailtrap::inbox.test.subtitle', ['mailer' => config('mail.default')]) }}</flux:subheading>
            </div>

            @if ($testResult)
                <flux:callout :variant="$testResult['ok'] ? 'success' : 'danger'" :icon="$testResult['ok'] ? 'check-circle' : 'exclamation-triangle'">
                    {{ $testResult['message'] }}
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>{{ __('mailtrap::inbox.email') }}</flux:label>
                <flux:input type="email" wire:model="testEmail" placeholder="name@example.com" />
                <flux:error name="testEmail" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('mailtrap::inbox.close') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    <span wire:loading.remove wire:target="sendTest">{{ __('mailtrap::inbox.send') }}</span>
                    <span wire:loading wire:target="sendTest">{{ __('mailtrap::inbox.sending') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
