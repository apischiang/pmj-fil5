@php
    $state = $get('file_attachment');
    $url = null;
    $extension = null;
    $isPdf = false;
    $isImage = false;
    $error = null;
    $debugInfo = [];

    if ($state) {
        // Handle array (if multiple) or single
        $file = is_array($state) ? ($state[0] ?? null) : $state;
        
        $debugInfo['file_type'] = gettype($file);
        
        if ($file) {
            if (is_string($file)) {
                $debugInfo['source'] = 'string path';
                // Saved file path
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                try {
                    // Try temporary URL first (for private/S3 buckets)
                    $url = \Illuminate\Support\Facades\Storage::disk('supabase')->temporaryUrl(
                        $file,
                        now()->addMinutes(10)
                    );
                    $debugInfo['url_generated'] = 'temporaryUrl';
                } catch (\Throwable $e) {
                    $debugInfo['temp_url_error'] = $e->getMessage();
                    // Fallback to public URL or silence
                    try {
                         $url = \Illuminate\Support\Facades\Storage::disk('supabase')->url($file);
                         $debugInfo['url_generated'] = 'publicUrl';
                    } catch (\Throwable $e2) {
                        $error = "Could not generate preview URL: " . $e->getMessage();
                        $debugInfo['public_url_error'] = $e2->getMessage();
                    }
                }
            } elseif (is_object($file) && method_exists($file, 'temporaryUrl')) {
                 $debugInfo['source'] = 'temporary uploaded file';
                 // TemporaryUploadedFile (Livewire)
                 try {
                    $url = $file->temporaryUrl();
                    $extension = strtolower($file->getClientOriginalExtension());
                    $debugInfo['url_generated'] = 'livewire_temp';
                 } catch (\Throwable $e) {
                    $error = "Temporary file preview error: " . $e->getMessage();
                    $debugInfo['livewire_error'] = $e->getMessage();
                 }
            } else {
                $debugInfo['source'] = 'unknown';
            }
            
            if ($extension === 'pdf') {
                $isPdf = true;
            } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
                $isImage = true;
            }
            $debugInfo['extension'] = $extension;
        }
    } else {
        $debugInfo['state'] = 'empty';
    }
@endphp

@if ($state)
    <div 
        x-data="{ 
            showModal: false,
            toggleModal() {
                this.showModal = !this.showModal;
            }
        }" 
        class="mt-4"
    >
        <!-- Trigger Button -->
        <button
            type="button"
            x-on:click="toggleModal()"
            @if(!$url) disabled @endif
            class="
                fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm w-full
                {{ $url 
                    ? 'fi-btn-color-gray bg-white text-gray-950 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 ring-1 ring-gray-950/10 dark:ring-white/20' 
                    : 'opacity-50 cursor-not-allowed bg-gray-100 text-gray-400 border border-gray-200' 
                }}
            "
        >
            <x-filament::icon
                icon="heroicon-o-eye"
                class="w-5 h-5 {{ $url ? 'text-gray-500 dark:text-gray-400' : 'text-gray-400' }}"
            />
            <span>
                {{ $url ? 'View Document Preview' : 'Preview Unavailable' }}
            </span>
        </button>

        @if($url)
            <!-- Modal Backdrop & Content -->
            <template x-teleport="body">
                <div
                    x-show="showModal"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-[99999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
                    style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 99999; display: flex; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.5);"
                >
                    <!-- Modal Box -->
                    <div 
                        @click.away="showModal = false"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="bg-white dark:bg-gray-900 rounded-xl shadow-2xl w-full max-w-5xl flex flex-col relative ring-1 ring-gray-950/5 dark:ring-white/10"
                        style="width: 90%; max-width: 80rem; height: 85vh; max-height: 90vh; display: flex; flex-direction: column; position: relative; margin: auto;"
                    >
                        <!-- Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800"
                             style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                            <h3 class="text-lg font-semibold text-gray-950 dark:text-white" style="font-size: 1.125rem; font-weight: 600;">
                                Document Preview
                            </h3>
                            <button 
                                type="button"
                                @click="showModal = false"
                                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 transition-colors focus:outline-none"
                                style="background: none; border: none; cursor: pointer; padding: 4px;"
                            >
                                <x-filament::icon
                                    icon="heroicon-o-x-mark"
                                    class="w-6 h-6 text-gray-500 dark:text-gray-400"
                                />
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 overflow-hidden bg-gray-50 dark:bg-gray-950/50 p-4 relative rounded-b-xl"
                             style="flex: 1; overflow: hidden; padding: 1rem; position: relative;">
                            @if ($isPdf)
                                <iframe 
                                    src="{{ $url }}" 
                                    class="w-full h-full rounded-lg border border-gray-200 dark:border-gray-800 shadow-sm bg-white" 
                                    style="width: 100%; height: 100%; border: none; background-color: white;"
                                    frameborder="0"
                                ></iframe>
                            @elseif ($isImage)
                                <div class="w-full h-full flex items-center justify-center overflow-auto"
                                     style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; overflow: auto;">
                                    <img 
                                        src="{{ $url }}" 
                                        alt="Document Attachment" 
                                        class="max-w-full max-h-full object-contain shadow-lg rounded-lg" 
                                        style="max-width: 100%; max-height: 100%; object-fit: contain;"
                                    />
                                </div>
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-center p-6">
                                    <div class="bg-gray-100 dark:bg-gray-800 rounded-full p-6 mb-4">
                                        <x-filament::icon
                                            icon="heroicon-o-document"
                                            class="w-16 h-16 text-gray-400"
                                        />
                                    </div>
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                                        Preview not available
                                    </h4>
                                    <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-sm">
                                        This file type cannot be previewed directly in the browser. You can download it to view locally.
                                    </p>
                                    <a
                                        href="{{ $url }}"
                                        target="_blank"
                                        class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-primary-600 text-white hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400 ring-1 ring-primary-600/10 dark:ring-primary-400/20"
                                    >
                                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-5 h-5" />
                                        <span>Download File</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </template>
        @endif
    </div>
@endif
