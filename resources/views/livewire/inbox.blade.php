<div class="mx-auto max-w-7xl space-y-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Mailtrap inbox</flux:heading>
            <flux:subheading>Controleer of uitgaande mail werkt — elke verzending wordt hier gelogd.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="$refresh" icon="arrow-path" variant="ghost">Vernieuwen</flux:button>
            <flux:button
                wire:click="cleanup"
                wire:confirm="Alle logs ouder dan {{ (int) config('manta_mailtrap.logging.cleanup_after_days', 30) }} dagen verwijderen?"
                icon="trash"
                variant="subtle"
            >Opschonen</flux:button>
            <flux:modal.trigger name="send-test">
                <flux:button icon="paper-airplane" variant="primary">Testmail versturen</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if ($notice)
        <flux:callout variant="success" icon="check-circle" x-data x-init="setTimeout(() => $wire.set('notice', null), 4000)">
            {{ $notice }}
        </flux:callout>
    @endif

    {{-- Statistieken --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <flux:card class="space-y-1">
            <flux:text size="sm">Totaal</flux:text>
            <flux:heading size="lg">{{ number_format($this->stats['total'], 0, ',', '.') }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">Verzonden</flux:text>
            <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ number_format($this->stats['sent'], 0, ',', '.') }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">In afwachting</flux:text>
            <flux:heading size="lg" class="text-yellow-600 dark:text-yellow-400">{{ number_format($this->stats['pending'], 0, ',', '.') }}</flux:heading>
        </flux:card>
        <flux:card class="space-y-1">
            <flux:text size="sm">Mislukt</flux:text>
            <flux:heading size="lg" class="text-red-600 dark:text-red-400">{{ number_format($this->stats['failed'], 0, ',', '.') }}</flux:heading>
        </flux:card>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input
            wire:model.live.debounce.400ms="search"
            icon="magnifying-glass"
            placeholder="Zoek op ontvanger, afzender of onderwerp…"
            class="sm:max-w-md"
        />
        <flux:select wire:model.live="status" class="sm:max-w-xs">
            <flux:select.option value="">Alle statussen</flux:select.option>
            <flux:select.option value="sent">Verzonden</flux:select.option>
            <flux:select.option value="pending">In afwachting</flux:select.option>
            <flux:select.option value="failed">Mislukt</flux:select.option>
            <flux:select.option value="blocked">Geblokkeerd</flux:select.option>
        </flux:select>
    </div>

    {{-- Tabel --}}
    <flux:table :paginate="$this->logs">
        <flux:table.columns>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Ontvanger</flux:table.column>
            <flux:table.column>Onderwerp</flux:table.column>
            <flux:table.column>Afzender</flux:table.column>
            <flux:table.column>Tijd</flux:table.column>
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
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">Geen mails gevonden.</flux:table.cell>
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
                    <flux:heading size="lg">Maildetails</flux:heading>
                    <flux:badge :color="$meta['color']">{{ $meta['label'] }}</flux:badge>
                </div>

                <flux:separator />

                <dl class="grid grid-cols-3 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-zinc-500">Ontvanger</dt>
                    <dd class="col-span-2 font-medium">{{ $this->selectedLog->recipient }}</dd>

                    <dt class="text-zinc-500">Afzender</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->sender }}</dd>

                    <dt class="text-zinc-500">Onderwerp</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->subject ?: '—' }}</dd>

                    <dt class="text-zinc-500">Status</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->status_code ?? 'in afwachting' }}</dd>

                    @if ($this->selectedLog->error_message)
                        <dt class="text-zinc-500">Foutmelding</dt>
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
                        <dt class="text-zinc-500">Bron</dt>
                        <dd class="col-span-2 break-all font-mono text-xs">{{ $this->selectedLog->source_file }}:{{ $this->selectedLog->source_line }}</dd>
                    @endif

                    <dt class="text-zinc-500">Message-ID</dt>
                    <dd class="col-span-2 break-all font-mono text-xs">{{ $this->selectedLog->message_id }}</dd>

                    <dt class="text-zinc-500">Tijdstip</dt>
                    <dd class="col-span-2">{{ $this->selectedLog->created_at?->format('d-m-Y H:i:s') }}</dd>
                </dl>

                <flux:separator />

                <div class="flex justify-between gap-2">
                    <flux:button
                        wire:click="deleteLog({{ $this->selectedLog->id }})"
                        wire:confirm="Deze mail-log verwijderen?"
                        icon="trash"
                        variant="danger"
                    >Verwijderen</flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Sluiten</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Testmail versturen --}}
    <flux:modal name="send-test" class="md:w-96">
        <form wire:submit="sendTest" class="space-y-4">
            <div>
                <flux:heading size="lg">Testmail versturen</flux:heading>
                <flux:subheading>Verstuurt een mail via de actieve mailer ({{ config('mail.default') }}) en logt het resultaat.</flux:subheading>
            </div>

            @if ($testResult)
                <flux:callout :variant="$testResult['ok'] ? 'success' : 'danger'" :icon="$testResult['ok'] ? 'check-circle' : 'exclamation-triangle'">
                    {{ $testResult['message'] }}
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>E-mailadres</flux:label>
                <flux:input type="email" wire:model="testEmail" placeholder="naam@voorbeeld.nl" />
                <flux:error name="testEmail" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Sluiten</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    <span wire:loading.remove wire:target="sendTest">Versturen</span>
                    <span wire:loading wire:target="sendTest">Bezig…</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
