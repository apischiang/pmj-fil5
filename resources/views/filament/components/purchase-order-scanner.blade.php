<x-filament::section>
    <div
        x-data="purchaseOrderScanner()"
        class="flex flex-col gap-4"
    >
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-document-magnifying-glass"
                    class="w-5 h-5 text-gray-500"
                />
                <h2 class="text-lg font-bold text-gray-700 dark:text-gray-200">Smart PO Scanner</h2>
            </div>
            <span class="text-xs text-gray-500">Powered by Tesseract.js</span>
        </div>

        <div class="text-sm text-gray-600 dark:text-gray-400">
            Drag & Drop a Purchase Order (Image/PDF) here to auto-fill the form.
        </div>

        <!-- Dropzone -->
        <div
            @dragover.prevent="dragover = true"
            @dragleave.prevent="dragover = false"
            @drop.prevent="handleDrop($event)"
            :class="{ 
                'border-primary-500 bg-primary-50 dark:bg-primary-900/10': dragover, 
                'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900': !dragover 
            }"
            class="relative flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors"
            style="display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; min-height: 160px;"
        >
            <input 
                type="file" 
                accept="image/*,application/pdf"
                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                @change="handleFileSelect"
                style="opacity: 0; position: absolute; inset: 0; width: 100%; height: 100%; cursor: pointer; z-index: 10;"
            >
            
            <div class="flex flex-col items-center justify-center pt-5 pb-6 pointer-events-none" x-show="!processing" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <svg class="w-10 h-10 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 40px; height: 40px; margin-bottom: 0.75rem; color: #9ca3af;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG or PDF</p>
            </div>

            <div class="flex flex-col items-center justify-center w-full px-12 pointer-events-none" x-show="processing" style="display: none;">
                <div class="flex justify-between w-full mb-1">
                    <span class="text-sm font-medium text-primary-700 dark:text-primary-400">Scanning...</span>
                    <span class="text-sm font-medium text-primary-700 dark:text-primary-400" x-text="Math.round(progress) + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                    <div class="bg-primary-600 h-2.5 rounded-full transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500" x-text="statusText"></p>
            </div>
        </div>
        
        <!-- Debug/Output Area -->
        <div x-show="debugMode && rawText" class="mt-2">
            <details>
                <summary class="text-xs cursor-pointer text-gray-500">Show Raw OCR Text</summary>
                <pre class="p-2 mt-1 bg-gray-100 dark:bg-gray-800 rounded text-xs font-mono h-32 overflow-y-auto whitespace-pre-wrap" x-text="rawText"></pre>
            </details>
        </div>
    </div>

    <!-- Tesseract CDN -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

    <script>
        function purchaseOrderScanner() {
            return {
                dragover: false,
                processing: false,
                progress: 0,
                statusText: 'Initializing...',
                rawText: '',
                debugMode: true, // Enabled for user feedback

                init() {
                    // Check if Tesseract is loaded
                    if (typeof Tesseract === 'undefined') {
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
                        script.onload = () => console.log('Tesseract loaded dynamically');
                        document.head.appendChild(script);
                    }
                },

                handleDrop(e) {
                    this.dragover = false;
                    const files = e.dataTransfer.files;
                    if (files.length > 0) this.validateAndProcess(files[0]);
                },

                handleFileSelect(e) {
                    const files = e.target.files;
                    if (files.length > 0) this.validateAndProcess(files[0]);
                    // Reset input
                    e.target.value = '';
                },

                validateAndProcess(file) {
                    if (file.type.startsWith('image/')) {
                        this.processFile(file);
                    } else if (file.type === 'application/pdf') {
                        this.processPdf(file);
                    } else {
                        this.processing = true;
                        this.statusText = 'Error: Only Image (JPG, PNG) or PDF files are supported.';
                        setTimeout(() => { 
                            this.processing = false; 
                        }, 3000);
                    }
                },

                // --- Core Logic ---

                async getTesseractWorker() {
                    if (typeof Tesseract === 'undefined') {
                        throw new Error('Tesseract.js is not loaded yet.');
                    }
                    const worker = await Tesseract.createWorker('eng', 1, {
                        logger: m => {
                            if (m.status === 'recognizing text') {
                                // Calculate global progress if possible, otherwise local
                                // For simplicity, we just show "Recognizing..."
                            }
                        }
                    });
                    return worker;
                },

                async processPdf(file) {
                    this.processing = true;
                    this.progress = 0;
                    this.statusText = 'Loading PDF Engine...';
                    this.rawText = '';

                    try {
                        if (typeof pdfjsLib === 'undefined') {
                            await this.loadPdfJs();
                        }
                        
                        this.statusText = 'Reading PDF...';
                        const arrayBuffer = await file.arrayBuffer();
                        const loadingTask = pdfjsLib.getDocument({ data: arrayBuffer });
                        const pdf = await loadingTask.promise;
                        
                        const numPages = pdf.numPages;
                        this.statusText = `Found ${numPages} page(s). Initializing OCR...`;
                        
                        // Init Worker Once
                        const worker = await this.getTesseractWorker();
                        
                        let combinedText = '';

                        for (let i = 1; i <= numPages; i++) {
                            this.statusText = `Processing Page ${i} of ${numPages}...`;
                            
                            // Render Page
                            const page = await pdf.getPage(i);
                            const scale = 2.0; 
                            const viewport = page.getViewport({ scale });
                            
                            const canvas = document.createElement('canvas');
                            const context = canvas.getContext('2d');
                            canvas.height = viewport.height;
                            canvas.width = viewport.width;
                            
                            await page.render({
                                canvasContext: context,
                                viewport: viewport
                            }).promise;
                            
                            const imageData = canvas.toDataURL('image/png');
                            
                            // OCR this page
                            const ret = await worker.recognize(imageData);
                            const pageText = ret.data.text;
                            
                            combinedText += `\n--- PAGE ${i} ---\n` + pageText;
                            
                            // Update progress bar roughly
                            this.progress = (i / numPages) * 100;
                        }

                        await worker.terminate();
                        
                        this.rawText = combinedText;
                        this.statusText = 'Parsing Data...';
                        await this.parseAndFill(combinedText);
                        
                        this.statusText = 'Complete!';
                        setTimeout(() => { this.processing = false; }, 1500);

                    } catch (err) {
                        console.error('PDF Error:', err);
                        this.statusText = 'PDF Error: ' + (err.message || 'Unknown error');
                    }
                },

                async processFile(input) {
                    this.processing = true;
                    this.progress = 0;
                    this.statusText = 'Initializing OCR...';
                    this.rawText = '';

                    try {
                        let imageData;
                        if (typeof input === 'string') {
                            imageData = input;
                        } else {
                            this.statusText = 'Reading file...';
                            imageData = await this.readFileAsDataURL(input);
                        }

                        const worker = await this.getTesseractWorker();
                        
                        this.statusText = 'Recognizing text...';
                        const ret = await worker.recognize(imageData);
                        this.progress = 100;
                        
                        await worker.terminate();
                        
                        this.rawText = ret.data.text;
                        this.statusText = 'Parsing Data...';
                        await this.parseAndFill(ret.data.text);
                        
                        this.statusText = 'Complete!';
                        setTimeout(() => { this.processing = false; }, 1500);

                    } catch (err) {
                        console.error('Scanner Error:', err);
                        this.statusText = 'Error: ' + (err.message || 'Unknown error');
                    }
                },

                loadPdfJs() {
                    return new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                        script.onload = () => {
                            // Set worker source
                            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                            resolve();
                        };
                        script.onerror = () => reject(new Error('Failed to load PDF.js'));
                        document.head.appendChild(script);
                    });
                },

                readFileAsDataURL(file) {
                    return new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(reader.result);
                        reader.onerror = () => reject(new Error('Failed to read file'));
                        reader.readAsDataURL(file);
                    });
                },

                async parseAndFill(text) {
                    const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);
                    
                    // --- 1. Header Extraction (Enhanced) ---
                    let poNumber = null;
                    let poDate = null;

                    // Regex Patterns (Relaxed)
                    const poPatterns = [
                        /(?:PO|Purchase Order|Order|No|Ref)\s*[:#.]?\s*([A-Za-z0-9\-\/]{3,})/i,
                        /([A-Z]{2,}-\d{3,})/ // Matches patterns like PO-2023-001 without label
                    ];
                    
                    const datePatterns = [
                        /(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/, // DD-MM-YYYY
                        /(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})/, // YYYY-MM-DD
                        /(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]* \d{1,2},? \d{4}/i // Month DD, YYYY
                    ];

                    // Scan Top 100 lines for Headers
                    for (let i = 0; i < Math.min(lines.length, 100); i++) {
                        const line = lines[i];
                        
                        // PO Number Strategy
                        if (!poNumber) {
                            for (const pat of poPatterns) {
                                const match = line.match(pat);
                                if (match && match[1]) {
                                    // Validate candidate: 
                                    // - Must have digits (to avoid "Order No")
                                    // - Length > 2
                                    const candidate = match[1].trim();
                                    if (candidate.length > 2 && /\d/.test(candidate) && !candidate.match(/^(No|Order|Ref|Date|Page)$/i)) {
                                        poNumber = candidate;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Date Strategy
                        if (!poDate) {
                            for (const pat of datePatterns) {
                                const match = line.match(pat);
                                if (match) {
                                    // Validate date string length and structure
                                    if (match[0].length >= 8) {
                                        poDate = this.formatDate(match[0]);
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    // Set Header Fields via Livewire
                    if (poNumber) this.$wire.set('data.po_number', poNumber);
                    if (poDate) this.$wire.set('data.po_date', poDate);
                    // Grand Total logic removed as requested

                    // --- 2. Item Extraction (Smart Type Detection) ---
                    let items = [];
                    let inTable = false;
                    
                    for (let i = 0; i < lines.length; i++) {
                        const line = lines[i];
                        const lower = line.toLowerCase();
                        
                        // Detect Table Header (Generic)
                        if ((lower.includes('description') || lower.includes('item') || lower.includes('material') || lower.includes('product')) && 
                            (lower.includes('qty') || lower.includes('quantity') || lower.includes('price') || lower.includes('amount'))) {
                            inTable = true;
                            continue;
                        }

                        if (inTable) {
                            // Stop conditions (Footer keywords)
                            if (lower.match(/^(total|subtotal|notes|tax|vat|gst|grand total|amount due|balance|payment|bank|transfer)/)) {
                                 if (lower.includes('grand total') || lower.includes('balance') || lower.includes('amount due')) break;
                                 // Usually these are footer lines, so we should skip them as potential items
                                 continue;
                            }
                            
                            // Skip Page markers
                            if (lower.includes('page') && lower.match(/\d/)) continue;
                            if (line.includes('--- PAGE')) continue;

                            // Skip common noise lines in POs (Address, Phone, etc within table area??)
                            if (lower.match(/^(tel|fax|phone|email|web|www|http|ibn|swift|bic|acc|account|reg|npwp)/)) continue;

                            // --- SMART ROW PARSING ---
                            // Strategy: Extract all numbers, identify Price & Qty, rest is Description.
                            
                            // 1. Extract all numbers (with decimals/commas)
                            const numberPattern = /[$€£Rp]?\s*(\d{1,3}(?:[.,]\d{3})*(?:[.,]\d+)?)/g;
                            let numbers = [];
                            let match;
                            while ((match = numberPattern.exec(line)) !== null) {
                                let raw = match[1].replace(/,/g, ''); 
                                if (!isNaN(parseFloat(raw))) {
                                    numbers.push({
                                        value: parseFloat(raw),
                                        index: match.index,
                                        raw: match[0]
                                    });
                                }
                            }

                            // Need at least 2 numbers (Qty and Price) to be a valid item line
                            if (numbers.length >= 2) {
                                let qty = 1;
                                let unitPrice = 0;
                                let description = '';
                                let isValidItem = false;
                                
                                // Take the last two numbers as candidates
                                const lastNum = numbers[numbers.length - 1];
                                const secondLastNum = numbers[numbers.length - 2];
                                
                                // Logic: Check if A * B = C (Strict Math Validation preferred)
                                if (numbers.length >= 3) {
                                    const thirdLastNum = numbers[numbers.length - 3];
                                    
                                    // Check: Qty * Price = Total
                                    // Toleransi 1.0 untuk pembulatan
                                    if (Math.abs((thirdLastNum.value * secondLastNum.value) - lastNum.value) < 1.0) {
                                        qty = thirdLastNum.value;
                                        unitPrice = secondLastNum.value;
                                        description = line.substring(0, thirdLastNum.index).trim();
                                        isValidItem = true;
                                    } 
                                    // Check: Price * Qty = Total (urutan terbalik?)
                                    else if (Math.abs((secondLastNum.value * thirdLastNum.value) - lastNum.value) < 1.0) {
                                        qty = secondLastNum.value;
                                        unitPrice = thirdLastNum.value;
                                        description = line.substring(0, thirdLastNum.index).trim();
                                        isValidItem = true;
                                    }
                                } 
                                
                                // Fallback: Jika matematika tidak pas, atau hanya ada 2 angka.
                                // Kita harus lebih Strict di sini agar tidak menangkap sampah.
                                if (!isValidItem) {
                                    // Syarat Fallback:
                                    // 1. Deskripsi harus cukup panjang (min 3 huruf)
                                    // 2. Tidak boleh mengandung kata kunci "Footer" di deskripsi (untuk safety)
                                    
                                    const tempDesc = line.substring(0, secondLastNum.index).trim();
                                    const tempLower = tempDesc.toLowerCase();
                                    
                                    const isNoise = tempLower.match(/(tax|vat|discount|disc|subtotal|total|shipping|freight|handling)/);
                                    
                                    if (tempDesc.length > 2 && !isNoise) {
                                        // Heuristic Type Detection
                                        const n1 = secondLastNum.value;
                                        const n2 = lastNum.value;
                                        
                                        // Asumsi: Qty biasanya Integer kecil, Price bisa desimal/besar
                                        if (Number.isInteger(n1) && n1 < 1000 && (!Number.isInteger(n2) || n2 > n1)) {
                                            qty = n1;
                                            unitPrice = n2;
                                            isValidItem = true;
                                        } 
                                        else if (Number.isInteger(n2) && n2 < 1000 && (!Number.isInteger(n1) || n1 > n2)) {
                                            qty = n2;
                                            unitPrice = n1;
                                            isValidItem = true;
                                        }
                                        // Jika sama-sama float atau sama-sama int besar -> Suspicious (mungkin no telp & kode pos?)
                                        // Kecuali jika formatnya sangat "rapi" (misal ada currency symbol di raw text).
                                        else if (lastNum.raw.match(/[$€£Rp]/) || secondLastNum.raw.match(/[$€£Rp]/)) {
                                             // Salah satu punya simbol mata uang, likely Price.
                                             if (lastNum.raw.match(/[$€£Rp]/)) {
                                                 unitPrice = lastNum.value;
                                                 qty = secondLastNum.value;
                                             } else {
                                                 unitPrice = secondLastNum.value;
                                                 qty = lastNum.value;
                                             }
                                             isValidItem = true;
                                        }
                                    }
                                    
                                    if (isValidItem) {
                                        description = tempDesc;
                                    }
                                }
                                
                                if (isValidItem) {
                                    // Clean description
                                    description = description.replace(/^\d+[\.\)]\s+/, ''); // Remove leading line numbers "1. ", "1 "
                                    description = description.replace(/^(item|desc|description)\s*/i, ''); // Remove header repetition

                                    if (description.length > 1) { // Min 2 chars
                                        items.push({
                                            item_sequence: items.length + 1,
                                            description: description,
                                            quantity: qty,
                                            uom: 'pcs', 
                                            unit_price: unitPrice,
                                            net_value: qty * unitPrice,
                                            material_number: '', 
                                        });
                                    }
                                }
                            }
                        }
                    }

                    if (items.length > 0) {
                        this.$wire.set('data.items', items);
                    }
                },

                formatDate(dateStr) {
                    // Normalize separators
                    dateStr = dateStr.replace(/[\/\.]/g, '-');
                    const parts = dateStr.split('-');
                    
                    // If YYYY-MM-DD
                    if (parts[0].length === 4) return dateStr;
                    
                    // If DD-MM-YYYY (or MM-DD-YYYY, hard to know, assume DD-MM)
                    if (parts[2].length === 4) {
                        return `${parts[2]}-${parts[1]}-${parts[0]}`;
                    }
                    return dateStr;
                }
            }
        }
    </script>
</x-filament::section>