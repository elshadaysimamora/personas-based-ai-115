// resources/views/documents/show.blade.php
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h1 class="text-2xl font-bold mb-4">{{ $document->title }}</h1>
                    
                    @if(Str::endsWith($document->file, '.pdf'))
                        <embed src="{{ Storage::url($document->file) }}" 
                               type="application/pdf" 
                               width="100%" 
                               height="600px" />
                    @else
                        <div class="whitespace-pre-wrap">
                            {{ Storage::get('public/' . $document->file) }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>