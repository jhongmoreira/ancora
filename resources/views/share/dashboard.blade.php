<x-share-layout :patient="$patient" :token="$token" :expires-at="$expiresAt">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <livewire:dashboard :patient="$patient" :read-only="true" :share-token="$token" />
</x-share-layout>
