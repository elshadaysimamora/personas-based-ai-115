<?php

namespace App\Livewire\Components;

use Livewire\Component;

class ChatPanel extends Component
{

    public $conversations;
    public $user;
    public $editingConversationId = null;
    public $conversationToDelete = null;
    protected $listeners = [
        'conversationListUpdated' => 'loadConversations',
    ];



    public function handleConversationDeleted($data = null)
    {
        $this->loadConversations(); // Refresh conversations
    }


    public function mount()
    {
        $this->loadConversations();
        $this->user = auth()->user();
    }

    public function loadConversations()
    {
        $this->conversations = auth()->user()->conversations()
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function createNewChat()
    {
        $conversation = auth()->user()->conversations()->create([
            'model' => 'gpt-rag',
        ]);
        $this->loadConversations();
        return redirect()->route('chat.show', $conversation);
    }


    // App\Livewire\Components\ChatPanel.php

    public function deleteConversation($conversationId) 
    {
        $conversation = auth()->user()->conversations()->find($conversationId);
        if (!$conversation) {
            return;
        }
    
        // Jika percakapan yang dihapus adalah percakapan yang sedang dibuka
        if ($conversation->uuid === session('current_conversation_uuid')) {
            // Buat percakapan baru sebagai pengganti
            $newConversation = auth()->user()->conversations()->create([
                'model' => 'gpt-rag',
            ]);
            session(['current_conversation_uuid' => $newConversation->uuid]);
    
            // Hapus percakapan lama
            $conversation->delete();
    
            // Refresh daftar percakapan dan pindah ke percakapan baru
            $this->loadConversations();
            return redirect()->route('chat.show', $newConversation);
        }
    
        // Hapus percakapan
        $conversation->delete();
    
        // Refresh daftar percakapan
        $this->loadConversations();
    
        // Emit event agar sidebar daftar percakapan ikut diperbarui
        $this->dispatch('conversationListUpdated');
    }
    

    public function render()
    {
        return view('livewire.components.chat-panel');
    }
}
