{{-- Chips de sentimentos agrupados por categoria de humor. Espera $moment ('before'|'after') e $selected (array de ids). --}}
<div class="space-y-3">
    @foreach ($this->moodCategories as $category)
        <div>
            <p class="text-xs text-gray-500 mb-1">{{ $category->label }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($category->feelings as $feeling)
                    <button
                        type="button"
                        wire:key="feeling-{{ $moment }}-{{ $feeling->id }}"
                        wire:click="toggleFeeling('{{ $moment }}', {{ $feeling->id }})"
                        class="px-3 py-1.5 rounded-full border-2 text-sm
                            {{ in_array($feeling->id, $selected) ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:border-gray-300' }}"
                    >
                        {{ $feeling->name }}
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
