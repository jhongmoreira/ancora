<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 lg:-my-px lg:ms-10 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('emotion-logs.create')" :active="request()->routeIs('emotion-logs.create')" wire:navigate>
                        {{ __('Registrar') }}
                    </x-nav-link>
                    <x-nav-link :href="route('emotion-logs.index')" :active="request()->routeIs('emotion-logs.index')" wire:navigate>
                        {{ __('Histórico') }}
                    </x-nav-link>
                    <x-nav-link :href="route('compulsions.index')" :active="request()->routeIs('compulsions.*')" wire:navigate>
                        {{ __('Compulsões') }}
                    </x-nav-link>
                    <x-nav-link :href="route('insights.index')" :active="request()->routeIs('insights.*')" wire:navigate>
                        {{ __('Insights') }}
                    </x-nav-link>
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.index')" wire:navigate>
                        {{ __('Relatórios') }}
                    </x-nav-link>
                    <x-nav-link :href="route('share.manage')" :active="request()->routeIs('share.manage')" wire:navigate>
                        {{ __('Compartilhar') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden lg:flex lg:items-center lg:ms-6">
                {{-- Menu do usuário: agrupa as páginas pessoais (Meus dados, Lembretes, Perfil) para aliviar a barra principal. --}}
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button @class([
                            'inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150',
                            'text-gray-900' => request()->routeIs('patient.edit', 'reminders.index', 'profile'),
                            'text-gray-500' => ! request()->routeIs('patient.edit', 'reminders.index', 'profile'),
                        ])>
                            <div
                                class="max-w-40 truncate whitespace-nowrap"
                                x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
                                x-text="name"
                                x-bind:title="name"
                                x-on:profile-updated.window="name = $event.detail.name"
                            >{{ auth()->user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('patient.edit')" wire:navigate :class="request()->routeIs('patient.edit') ? 'font-medium text-indigo-700 bg-indigo-50' : ''">
                            {{ __('Meus dados') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('reminders.index')" wire:navigate :class="request()->routeIs('reminders.index') ? 'font-medium text-indigo-700 bg-indigo-50' : ''">
                            {{ __('Lembretes') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('profile')" wire:navigate :class="request()->routeIs('profile') ? 'font-medium text-indigo-700 bg-indigo-50' : ''">
                            {{ __('Perfil e senha') }}
                        </x-dropdown-link>

                        <div class="border-t border-gray-100"></div>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Sair') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('emotion-logs.create')" :active="request()->routeIs('emotion-logs.create')" wire:navigate>
                {{ __('Registrar') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('emotion-logs.index')" :active="request()->routeIs('emotion-logs.index')" wire:navigate>
                {{ __('Histórico') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('compulsions.index')" :active="request()->routeIs('compulsions.*')" wire:navigate>
                {{ __('Compulsões') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('insights.index')" :active="request()->routeIs('insights.*')" wire:navigate>
                {{ __('Insights') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.index')" wire:navigate>
                {{ __('Relatórios') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('share.manage')" :active="request()->routeIs('share.manage')" wire:navigate>
                {{ __('Compartilhar') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name">{{ auth()->user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('patient.edit')" :active="request()->routeIs('patient.edit')" wire:navigate>
                    {{ __('Meus dados') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reminders.index')" :active="request()->routeIs('reminders.index')" wire:navigate>
                    {{ __('Lembretes') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('profile')" :active="request()->routeIs('profile')" wire:navigate>
                    {{ __('Perfil e senha') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Sair') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
