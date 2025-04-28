<div>
    <div x-data="{ modalNewChat: false, modalHistoryConversation: false }" class="flex relative">
        <button type="button" @click="modalNewChat = !modalNewChat"
            class="bg-gray-800 hover:bg-gray-900 text-white font-semibold p-1 mx-1 rounded-lg transition transform hover:scale-105">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        </button>
        <div x-cloak x-show="modalNewChat" @click.away="modalNewChat = false"
            class="absolute bottom-full mb-2 w-48 bg-gray-800 shadow-lg rounded-lg overflow-hidden">
            <button wire:click="createNewChat" wire:loading.attr="disabled"
                class="flex w-full gap-x-4 rounded-lg p-4 text-left text-sm font-medium text-white transition-colors duration-200 hover:bg-gray-900 focus:outline-none dark:border-slate-700 dark:hover:bg-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M12 5l0 14"></path>
                    <path d="M5 12l14 0"></path>
                </svg>
                New Chat
            </button>
        </div>
        {{-- history --}}
        <button type="button" @click="modalHistoryConversation = !modalHistoryConversation"
            class="bg-gray-800 hover:bg-gray-900 text-white font-semibold p-1 rounded-lg transition transform hover:scale-105">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </button>
        {{-- modal body --}}
        <div x-cloak x-show="modalHistoryConversation" @click.away="modalHistoryConversation = false"
            class="absolute bottom-full mb-2 w-96 bg-gray-800 shadow-lg rounded-lg overflow-hidden">
            <div class="border-b border-black/10 dark:border-white/10 sm:w-conversations"
                style="transform: none; transform-origin: 50% 50% 0px;">
                <div x-data="{
                    activeTab: 'with-rag',
                    tabs: [
                        {{-- { id: 'gen-ai', name: 'Gen AI', model: 'gpt' }, --}} { id: 'with-rag', name: 'AI Knowledge', model: 'gpt-rag' },
                        { id: 'rag-persona', name: 'AI Knowledge & Persona', model: 'gpt-rag-persona' }
                    ]
                }" class="w-full">
                    <!-- Tab Navigation -->
                    <div class="flex space-x-2 p-3 border-b border-white/10">
                        <template x-for="tab in tabs" :key="tab.id">
                            <button @click="activeTab = tab.id"
                                :class="{
                                    'bg-white/10 border-white/20 shadow-sm': activeTab === tab.id,
                                    'hover:bg-white/5 border-transparent': activeTab !== tab.id
                                }"
                                class="px-5 py-2.5 rounded-lg text-sm font-medium text-white transition-all duration-200 ease-in-out border flex items-center gap-2">
                                <!-- Icon for AI Knowledge -->
                                <svg x-show="tab.id === 'with-rag'" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                                <!-- Icon for AI Knowledge & Persona -->
                                <svg x-show="tab.id === 'rag-persona'" class="w-4 h-4" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span x-text="tab.name"></span>
                            </button>
                        </template>
                    </div>

                    <!-- Conversation Lists -->
                    <div
                        class="p-2 pt-6 flex h-96 max-h-expanded-section text-white w-full select-none flex-col gap-0.5 overflow-y-auto overflow-x-hidden rounded-xl scrollbar-container">
                        <div class="px-2 pt-0.5">
                            <h3 class="pb-5 text-lg font-bold">Our conversations together</h3>
                        </div>
                        <div class="mt-4 transition-all duration-200">
                            @foreach ($conversations as $conversation)
                                <div x-show="tabs.find(tab => tab.id === activeTab).model === '{{ $conversation->model }}'"
                                    class="max-h-12 min-h-12 cursor-pointer rounded-xl p-3 text-start text-sm hover:bg-black/5 focus:bg-black/5 active:bg-black/8 contrast-more:outline-2 contrast-more:focus:outline dark:fill-white dark:hover:bg-white/5 dark:focus:bg-white/5 dark:active:bg-white/8"
                                    role="option" tabindex="-1">
                                    <div class="flex size-full items-center justify-between">
                                        <a href="{{ route('chat.show', $conversation) }}" class="flex-1 truncate">
                                            {{ $conversation->title }}
                                        </a>
                                        <button wire:click="deleteConversation({{ $conversation->id }})"
                                            class="relative flex items-center justify-center text-foreground-800 fill-foreground-800 
                                            active:text-foreground-600 active:fill-foreground-600 dark:active:text-foreground-650 
                                            dark:active:fill-foreground-650 bg-transparent hover:bg-white/50 active:bg-white/35 
                                            dark:hover:bg-black/30 dark:active:bg-black/20 text-xs min-h-9 min-w-9 px-2.5 py-1 gap-x-1.5 
                                            rounded-xl outline-2 outline-offset-1 focus-visible:z-[1] focus-visible:outline 
                                            focus-visible:outline-stroke-900"
                                            title="Delete conversation">
                                            <svg viewBox="0 0 24 24" fill="currentColor"
                                                xmlns="http://www.w3.org/2000/svg" class="size-4">
                                                <path
                                                    d="M10 5H14C14 3.89543 13.1046 3 12 3C10.8954 3 10 3.89543 10 5ZM8.5 5C8.5 3.067 10.067 1.5 12 1.5C13.933 1.5 15.5 3.067 15.5 5H21.25C21.6642 5 22 5.33579 22 5.75C22 6.16421 21.6642 6.5 21.25 6.5H19.9309L18.7589 18.6112C18.5729 20.5334 16.9575 22 15.0263 22H8.97369C7.04254 22 5.42715 20.5334 5.24113 18.6112L4.06908 6.5H2.75C2.33579 6.5 2 6.16421 2 5.75C2 5.33579 2.33579 5 2.75 5H8.5ZM10.5 9.75C10.5 9.33579 10.1642 9 9.75 9C9.33579 9 9 9.33579 9 9.75V17.25C9 17.6642 9.33579 18 9.75 18C10.1642 18 10.5 17.6642 10.5 17.25V9.75ZM14.25 9C14.6642 9 15 9.33579 15 9.75V17.25C15 17.6642 14.6642 18 14.25 18C13.8358 18 13.5 17.6642 13.5 17.25V9.75C13.5 9.33579 13.8358 9 14.25 9ZM6.73416 18.4667C6.84577 19.62 7.815 20.5 8.97369 20.5H15.0263C16.185 20.5 17.1542 19.62 17.2658 18.4667L18.4239 6.5H5.57608L6.73416 18.4667Z">
                                                </path>
                                            </svg>
                                        </button>

                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
