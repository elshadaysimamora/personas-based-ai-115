<div class="flex flex-col h-screen" x-data="{
    text: '',
    temporaryMessage: '',
    sending: false,
    errorMessage: '',
    messageQueue: [],
    isTyping: false,

    // Fungsi untuk mengirim pesan 
    sendMessage() {
        if (!this.text.trim()) return;

        this.temporaryMessage = this.text;
        this.sending = true;

        // Simpan pesan pengguna ke database
        $wire.sendMessage(this.text)
            .then(() => {
                // Mulai proses respon
                this.processAIResponse();
            });

        this.text = '';
    },

    // Proses respons AI dengan penanganan kesalahan dan retry
    processAIResponse() {
        $wire.respond()
            .then(() => {
                this.sending = false;
                this.temporaryMessage = null;
                $wire.clearState();
                this.scrollToBottom();
            })
            .catch(error => {
                this.sending = false;
                this.errorMessage = error.message || 'Terjadi kesalahan. Silakan coba lagi.';
                setTimeout(() => { this.errorMessage = ''; }, 5000);
            });
    },

    // Scroll ke bagian bawah chat
    scrollToBottom() {
        setTimeout(() => {
            const chatContainer = document.querySelector('#chat-messages-container');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }, 100);
    },

    // Inisialisasi
    init() {
        this.scrollToBottom();

        // Listen to AI stream events
        window.addEventListener('stream-update', () => {
            this.scrollToBottom();
        });

        // Listen to AI error events
        $wire.on('aiError', ({ message }) => {
            this.errorMessage = message;
            this.sending = false;
            setTimeout(() => { this.errorMessage = ''; }, 5000);
        });
    }
}" x-init="init()">

    <!-- Header dengan model selection dan settings -->
    <div class="z-50 fixed top-4 right-4">
        <livewire:components.settings-modal :user="$user" />
    </div>

    <!-- Error Message Banner -->
    <div x-cloak x-show="errorMessage" x-transition
        class="bg-red-600 text-white px-4 py-2 text-sm flex justify-between items-center">
        <div class="flex items-center space-x-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                    clip-rule="evenodd" />
            </svg>
            <span x-text="errorMessage"></span>
        </div>
        <button @click="errorMessage = ''" class="text-white hover:text-gray-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                    clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    <!-- Messages Area - Scrollable -->
    <div id="chat-messages-container"
        class="flex-1 overflow-y-auto bg-gray-950 scrollbar-thin scrollbar-thumb-gray-700">
        <div class="max-w-3xl mx-auto p-4 space-y-6">
            <!-- Empty State -->
            @if ($conversation->messages->isEmpty())
                <div class="text-center text-gray-400 h-full flex flex-col justify-center items-center space-y-6 py-20">
                    <div class="text-5xl">💬</div>
                    <h1 x-data="typingEffectInHome()" x-init="startTyping()" x-text="displayText"
                        class="text-3xl sm:text-4xl font-semibold text-center mb-6 text-gray-200"></h1>
                    <p class="text-gray-500 max-w-md">Tanyakan apa saja untuk memulai percakapan. AI assistant akan
                        membantu Anda</p>
                </div>
            @else
                <ul class="space-y-6">
                    <!-- Messages -->
                    @foreach ($messages as $message)
                        <li class="{{ $message->is_user_message ? 'flex justify-end' : 'flex justify-start' }}"
                            id="message-{{ $loop->index }}">
                            <!-- User Message -->
                            @if ($message->is_user_message)
                                <div class="message bg-blue-800 px-4 py-3 text-white max-w-xl rounded-lg shadow-md">
                                    {{ $message->content }}
                                </div>
                            @else
                                <!-- AI Response -->
                                <div class="flex max-w-3xl w-full">
                                    <div
                                        class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center mr-3 flex-shrink-0 mt-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white"
                                            viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z" />
                                            <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z" />
                                        </svg>
                                    </div>
                                    <div id="aiResponse-{{ $loop->index }}"
                                        class="message text-gray-200 bg-gray-800 px-4 py-3 rounded-lg shadow-md chat-container w-full">
                                        {!! $message->content !!}
                                    </div>
                                </div>
                            @endif
                        </li>
                    @endforeach

                    <!-- Temporary User Message -->
                    <template x-if="temporaryMessage">
                        <li class="flex justify-end">
                            <div class="bg-blue-800 text-white px-4 py-3 rounded-lg shadow-md">
                                <p x-text="temporaryMessage"></p>
                            </div>
                        </li>
                    </template>

                    <!-- Streaming AI Response -->
                    <template x-if="sending">
                        <li class="flex justify-start">
                            <div class="flex max-w-3xl w-full">
                                <div
                                    class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center mr-3 flex-shrink-0 mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white"
                                        viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z" />
                                        <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z" />
                                    </svg>
                                </div>
                                <div
                                    class="prose prose-invert text-gray-200 bg-gray-800 px-4 py-3 rounded-lg shadow-md chat-container w-full">
                                    <div wire:stream="response" class="prose prose-invert max-w-none"></div>
                                    <div x-cloak x-show="!$wire.answer" class="typing-animation">
                                        <span class="dot"></span>
                                        <span class="dot"></span>
                                        <span class="dot"></span>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
            @endif
        </div>
    </div>

    <!-- Input Area -->
    <div class="p-4 bg-gray-950">
        <form @submit.prevent="sendMessage()" class="max-w-3xl mx-auto">
            <div class="flex items-center space-x-2 bg-gray-900 rounded-3xl p-3">
                <livewire:components.chat-panel />
                <div class="flex-grow relative">
                    <textarea x-model="text" @keydown.enter="if(!$event.shiftKey) sendMessage(); else $event.target.value += '\n'"
                        :disabled="sending" placeholder="Ketik pesan Anda..." rows="1"
                        class="w-full bg-gray-950 text-gray-200 border border-gray-700 rounded-3xl px-4 py-3 resize-none"
                        x-ref="messageInput" style="max-height: 200px;"
                        @input="$event.target.style.height = ''; $event.target.style.height = $event.target.scrollHeight + 'px'"></textarea>
                </div>
                <button type="submit" :disabled="!text.trim() || sending"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium p-3 rounded-lg transition transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-cloak x-show="!sending">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                        </svg>
                    </span>
                    <span x-cloak x-show="sending">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="current                             <path class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V2a10 10 0 00-10 10h2zm12-8v2a8 8 0 010 16h-2a10 10 0 0010-10h-2z">
                            </path>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>
