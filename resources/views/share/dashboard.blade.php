<x-share-layout :patient="$patient" :token="$token" :expires-at="$expiresAt">
    <livewire:dashboard :patient="$patient" :read-only="true" />
</x-share-layout>
