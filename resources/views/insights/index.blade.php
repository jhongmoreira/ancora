<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Insights') }}
        </h2>
    </x-slot>

    <div class="pt-6 pb-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <livewire:insights.report />
        </div>
    </div>
</x-app-layout>
