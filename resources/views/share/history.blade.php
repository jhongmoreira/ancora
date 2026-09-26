<x-share-layout :patient="$patient" :token="$token" :expires-at="$expiresAt">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Histórico</h2>
    </x-slot>

    <livewire:emotion-log.history :patient="$patient" :read-only="true" :share-token="$token" />
</x-share-layout>
