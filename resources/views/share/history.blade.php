<x-share-layout :patient="$patient" :token="$token" :expires-at="$expiresAt">
    <livewire:emotion-log.history :patient="$patient" :read-only="true" :share-token="$token" />
</x-share-layout>
