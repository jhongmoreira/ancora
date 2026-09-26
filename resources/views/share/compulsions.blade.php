<x-share-layout :patient="$patient" :token="$token" :expires-at="$expiresAt">
    <livewire:compulsion.history :patient="$patient" :read-only="true" :share-token="$token" />
</x-share-layout>
