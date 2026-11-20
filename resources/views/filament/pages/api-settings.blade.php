<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Enregistrer l’adresse
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
