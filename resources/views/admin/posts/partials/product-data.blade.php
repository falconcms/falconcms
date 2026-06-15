@if($type === 'product')
@php
    $productType = old('product_type', $post->shopData->type ?? 'simple');
    $attributesData = old('attributes_data', $post->shopData->attributes_data ?? []);
    // Load existing variations if any
    $variations = $post->shopData ? $post->shopData->variations()->get()->map(function($v) {
        return [
            'id' => $v->id,
            'attributes_data' => $v->attributes_data,
            'price' => $v->price,
            'sale_price' => $v->sale_price,
            'sku' => $v->sku,
            'weight' => $v->weight,
            'length' => $v->length,
            'width' => $v->width,
            'height' => $v->height,
            'stock_quantity' => $v->stock_quantity,
            'stock_status' => $v->stock_status,
            'manage_stock' => $v->manage_stock,
            'image' => $v->image
        ];
    }) : collect();
    // Pre-load download files for Alpine (computed here to avoid complex expression inside @json directive)
    $dlInitialFiles = $post->shopData
        ? $post->shopData->downloads()->orderBy('sort_order')->get()->map(function($d) {
            return [
                'id'             => $d->id,
                'name'           => $d->name,
                'file_size'      => $d->file_size,
                'download_limit' => $d->download_limit,
                'delete_url'     => route('admin.shop.products.downloads.destroy', $d->id),
            ];
          })->values()->all()
        : [];
@endphp
<script>
(function () {
    var _pdCfg = {
        productType:   {!! json_encode($productType) !!},
        manageStock:   {{ old('manage_stock', $post->shopData->manage_stock ?? false) ? 'true' : 'false' }},
        attributes:    {!! json_encode($attributesData) !!},
        variations:    {!! json_encode($variations) !!},
        stockQuantity: {{ old('stock_quantity', $post->shopData->stock_quantity ?? 0) }},
        stockStatus:   {!! json_encode(old('stock_status', $post->shopData->stock_status ?? 'instock')) !!},
        downloadFiles: {!! json_encode($dlInitialFiles) !!},
        ajaxSaveUrl:   {!! json_encode(route('admin.posts.variations.ajax-save', $post->id ?? 0)) !!},
        csrf:          {!! json_encode(csrf_token()) !!},
        dlUploadUrl:   {!! isset($post->shopData) ? json_encode(route('admin.shop.products.downloads.store', $post->shopData->id)) : 'null' !!}
    };

    function falconProductData() {
        return {
            productType:     _pdCfg.productType,
            activeTab:       'general',
            showUploadModal: false,
            dlFile: null, dlName: '', dlLimit: '', dlUploading: false, dlStatus: '', dlStatusType: '',
            downloadFiles:   _pdCfg.downloadFiles,
            manageStock:     _pdCfg.manageStock,
            attributes:      _pdCfg.attributes,
            variations:      _pdCfg.variations,
            variationAction: '',
            stockQuantity:   _pdCfg.stockQuantity,
            stockStatus:     _pdCfg.stockStatus,
            isSaving:        false,

            init() {
                this.activeTab = localStorage.getItem('lazy_product_active_tab') || 'general';
                this.$watch('activeTab', val => localStorage.setItem('lazy_product_active_tab', val));

                this.$watch('variations', function (value) {
                    value.forEach(function (v) {
                        if (v.manage_stock) {
                            v.stock_status = parseInt(v.stock_quantity) <= 0 ? 'outofstock' : 'instock';
                        }
                    });
                    if (this.productType === 'variable') {
                        var anyInStock = value.some(function (v) { return v.stock_status === 'instock'; });
                        this.stockStatus = anyInStock ? 'instock' : 'outofstock';
                    }
                }.bind(this), { deep: true });

                this.$watch('stockQuantity', function (value) {
                    if (this.manageStock) {
                        this.stockStatus = parseInt(value) <= 0 ? 'outofstock' : 'instock';
                    }
                }.bind(this));

                this.$watch('manageStock', function (value) {
                    if (value) {
                        this.stockStatus = parseInt(this.stockQuantity) <= 0 ? 'outofstock' : 'instock';
                    }
                }.bind(this));
            },

            async saveVariations() {
                if (this.isSaving) return;
                this.isSaving = true;
                try {
                    var resp = await fetch(_pdCfg.ajaxSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': _pdCfg.csrf
                        },
                        body: JSON.stringify({ variations: this.variations, attributes_data: this.attributes })
                    });
                    var data = await resp.json();
                    if (data.success) {
                        window.showToast ? window.showToast('Variations saved successfully!', 'success') : alert('Variations saved successfully!');
                    } else {
                        window.showToast ? window.showToast('Error: ' + (data.message || 'Unknown error'), 'error') : alert('Error: ' + (data.message || 'Unknown error'));
                    }
                } catch (e) {
                    console.error(e);
                    window.showToast ? window.showToast('Failed to save variations.', 'error') : alert('Failed to save variations.');
                } finally {
                    this.isSaving = false;
                }
            },

            addAttribute() {
                this.attributes.push({ name: '', values: '', visible: true, variation: true });
            },

            removeAttribute(index) {
                this.attributes.splice(index, 1);
            },

            addVariation() {
                var varAttrs = this.attributes.filter(function (a) { return a.variation && a.name && a.values; });
                if (varAttrs.length === 0) { alert('Please add at least one attribute marked for variations.'); return; }
                var combo = {};
                varAttrs.forEach(function (attr) {
                    var vals = attr.values.split('|').map(function (v) { return v.trim(); }).filter(Boolean);
                    combo[attr.name] = vals[0] || '';
                });
                this.variations.push({ attributes_data: combo, price: '', sale_price: '', sku: '', weight: '', length: '', width: '', height: '', stock_quantity: 0, stock_status: 'instock', manage_stock: false, image: '' });
                this.activeTab = 'variations';
            },

            generateVariations() {
                var variationAttributes = this.attributes.filter(function (a) { return a.variation && a.name && a.values; });
                if (variationAttributes.length === 0) { alert("Please add at least one attribute and check 'Used for variations'."); return; }
                if (!confirm('This will generate all possible combinations. Existing variation data will be reset. Continue?')) return;
                var combos = [{}];
                variationAttributes.forEach(function (attr) {
                    var vals = attr.values.split('|').map(function (v) { return v.trim(); }).filter(Boolean);
                    if (!vals.length) return;
                    var next = [];
                    combos.forEach(function (combo) { vals.forEach(function (val) { var c = Object.assign({}, combo); c[attr.name] = val; next.push(c); }); });
                    combos = next;
                });
                if (combos.length === 1 && Object.keys(combos[0]).length === 0) combos = [];
                this.variations = [];
                var self = this;
                combos.forEach(function (combo) {
                    if (!self.variations.some(function (v) { return JSON.stringify(v.attributes_data) === JSON.stringify(combo); })) {
                        self.variations.push({ attributes_data: combo, price: '', sale_price: '', sku: '', weight: '', length: '', width: '', height: '', stock_quantity: 0, stock_status: 'instock', manage_stock: false, image: '' });
                    }
                });
                this.activeTab = 'variations';
            },

            removeVariation(index) {
                if (confirm('Are you sure you want to remove this variation?')) this.variations.splice(index, 1);
            },

            selectDownloadFile() {
                var self = this;
                if (typeof window.openMediaModal === 'function') {
                    window.openMediaModal(function (media) {
                        self.dlFile = media;
                        if (!self.dlName) self.dlName = media.file_name || media.name || '';
                    });
                } else {
                    alert('Media library not available.');
                }
            },

            falconUploadDownload() {
                if (!this.dlFile) return;
                this.dlUploading = true;
                this.dlStatus = '';
                var self = this;
                fetch(_pdCfg.dlUploadUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _pdCfg.csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ media_path: this.dlFile.path, name: this.dlName.trim(), download_limit: this.dlLimit || null }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            self.downloadFiles.push(data.download);
                            self.dlFile = null; self.dlName = ''; self.dlLimit = '';
                            self.dlStatus = 'File added successfully!';
                            self.dlStatusType = 'success';
                            setTimeout(function () { self.showUploadModal = false; self.dlStatus = ''; }, 900);
                        } else {
                            self.dlStatus = data.message || 'Failed to add file.';
                            self.dlStatusType = 'error';
                        }
                    })
                    .catch(function () { self.dlStatus = 'An error occurred. Please try again.'; self.dlStatusType = 'error'; })
                    .finally(function () { self.dlUploading = false; });
            },

            falconDeleteDownload(index, url) {
                if (!confirm('Delete this file?')) return;
                var self = this;
                var token = document.querySelector('meta[name="csrf-token"]');
                fetch(url, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token ? token.content : _pdCfg.csrf } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { if (data.success) self.downloadFiles.splice(index, 1); });
            },

            selectVariationImage(vIndex) {
                var self = this;
                if (typeof window.openMediaModal === 'function') {
                    window.openMediaModal(function (media) { self.variations[vIndex].image = media.path; });
                } else {
                    alert('Media manager not found.');
                }
            }
        };
    }

    if (window.Alpine) {
        window.Alpine.data('falconProductData', falconProductData);
    } else {
        document.addEventListener('alpine:init', function () {
            window.Alpine.data('falconProductData', falconProductData);
        });
    }
})();
</script>
<div class="wp-metabox mt-6 mb-6" x-data="falconProductData()">
    <div class="wp-metabox-header flex justify-between items-center">
        <span>Product Data</span>
        <div class="flex items-center space-x-2 mr-4">
            <span class="text-[12px] font-medium text-[#646970]">Product Type:</span>
            <select name="product_type" x-model="productType" class="wp-input h-7 py-0 text-[12px] min-w-[150px]">
                <option value="simple">Simple product</option>
                <option value="variable">Variable product</option>
            </select>
        </div>
    </div>
    
    <div class="wp-metabox-content p-0 flex min-h-[400px]">
        <!-- Sidebar Tabs -->
        <div class="w-48 bg-[#f6f7f7] border-r border-[#f0f0f1] flex-shrink-0">
            <ul class="text-[13px]">
                <li>
                    <button type="button" @click="activeTab = 'general'" :class="activeTab === 'general' ? 'bg-white border-y border-[#f0f0f1] border-r-transparent -mr-[1px] text-[#2271b1] font-semibold' : 'text-[#2271b1] hover:bg-white'" class="w-full text-left px-4 py-2.5 transition-colors flex items-center space-x-2">
                        <span class="material-symbols-outlined text-[18px]">settings</span>
                        <span>General</span>
                    </button>
                </li>
                <li>
                    <button type="button" @click="activeTab = 'inventory'" :class="activeTab === 'inventory' ? 'bg-white border-y border-[#f0f0f1] border-r-transparent -mr-[1px] text-[#2271b1] font-semibold' : 'text-[#2271b1] hover:bg-white'" class="w-full text-left px-4 py-2.5 transition-colors flex items-center space-x-2">
                        <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                        <span>Inventory</span>
                    </button>
                </li>
                <li>
                    <button type="button" @click="activeTab = 'attributes'" :class="activeTab === 'attributes' ? 'bg-white border-y border-[#f0f0f1] border-r-transparent -mr-[1px] text-[#2271b1] font-semibold' : 'text-[#2271b1] hover:bg-white'" class="w-full text-left px-4 py-2.5 transition-colors flex items-center space-x-2">
                        <span class="material-symbols-outlined text-[18px]">list</span>
                        <span>Attributes</span>
                    </button>
                </li>
                <li x-show="productType === 'variable'">
                    <button type="button" @click="activeTab = 'variations'" :class="activeTab === 'variations' ? 'bg-white border-y border-[#f0f0f1] border-r-transparent -mr-[1px] text-[#2271b1] font-semibold' : 'text-[#2271b1] hover:bg-white'" class="w-full text-left px-4 py-2.5 transition-colors flex items-center space-x-2">
                        <span class="material-symbols-outlined text-[18px]">layers</span>
                        <span>Variations</span>
                    </button>
                </li>
                <li>
                    <button type="button" @click="activeTab = 'downloads'" :class="activeTab === 'downloads' ? 'bg-white border-y border-[#f0f0f1] border-r-transparent -mr-[1px] text-[#2271b1] font-semibold' : 'text-[#2271b1] hover:bg-white'" class="w-full text-left px-4 py-2.5 transition-colors flex items-center space-x-2">
                        <span class="material-symbols-outlined text-[18px]">download</span>
                        <span>Downloads</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="flex-grow p-6 bg-white overflow-hidden">
            <!-- General Tab -->
            <div x-show="activeTab === 'general'" class="space-y-6">
                <div x-show="productType === 'simple'" class="space-y-4">
                    <div class="grid grid-cols-3 items-center">
                        <label class="text-[13px] font-semibold text-[#1d2327]">Regular Price (৳)</label>
                        <div class="col-span-2">
                            <input type="number" name="price" id="regular_price" step="0.01" value="{{ old('price', $post->shopData->price ?? '') }}" class="wp-input w-full max-w-[300px]">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 items-center">
                        <label class="text-[13px] font-semibold text-[#1d2327]">Sale Price (৳)</label>
                        <div class="col-span-2">
                            <input type="number" name="sale_price" id="sale_price" step="0.01" value="{{ old('sale_price', $post->shopData->sale_price ?? '') }}" class="wp-input w-full max-w-[300px]">
                            <div id="price-error" class="hidden text-[#d63638] text-[11px] mt-1 italic">Sale price must be less than regular price.</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 items-center">
                        <label class="text-[13px] font-semibold text-[#1d2327]">Sale End Date</label>
                        <div class="col-span-2">
                            <input type="datetime-local" name="sale_ends_at" value="{{ old('sale_ends_at', isset($post->shopData->sale_ends_at) ? \Carbon\Carbon::parse($post->shopData->sale_ends_at)->format('Y-m-d\TH:i') : '') }}" class="wp-input w-full max-w-[300px]">
                            <p class="text-[11px] text-[#646970] mt-1">Leave blank for no expiry. Sale price is removed automatically when this date passes.</p>
                        </div>
                    </div>
                </div>

                <div x-show="productType === 'variable'" class="space-y-4">
                    <div class="bg-[#f0f6fc] border-l-4 border-[#0A66C2] p-4 rounded-sm">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <span class="material-symbols-outlined text-[#0A66C2]">info</span>
                            </div>
                            <div class="ml-3">
                                <p class="text-[13px] text-[#1d2327] font-medium">Variable Product Configuration</p>
                                <p class="text-[12px] text-[#646970] mt-1">For variable products, prices and stock are managed individually for each variation in the <strong>Variations</strong> tab.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 items-center">
                        <label class="text-[13px] font-semibold text-[#1d2327]">Tax Status</label>
                        <div class="col-span-2">
                            <select name="tax_status" class="wp-input h-8 py-0 w-full max-w-[300px]">
                                <option value="taxable">Taxable</option>
                                <option value="shipping">Shipping only</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div x-show="activeTab === 'inventory'" class="space-y-6">
                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">SKU</label>
                    <div class="col-span-2">
                        <input type="text" name="sku" value="{{ old('sku', $post->shopData->sku ?? '') }}" class="wp-input w-full max-w-[300px]" placeholder="Unique identifier">
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Manage Stock?</label>
                    <div class="col-span-2 flex items-center">
                        <input type="checkbox" name="manage_stock" value="1" x-model="manageStock" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                        <span class="text-[12px] text-[#646970]">Enable stock management at product level</span>
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center" x-show="manageStock">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Stock Quantity</label>
                    <div class="col-span-2">
                        <input type="number" name="stock_quantity" x-model="stockQuantity" class="wp-input w-24">
                    </div>
                </div>

                <div class="grid grid-cols-3 items-center">
                    <label class="text-[13px] font-semibold text-[#1d2327]">Stock Status</label>
                    <div class="col-span-2">
                        <select name="stock_status" x-model="stockStatus" class="wp-input h-8 py-0 w-full max-w-[300px]" :disabled="manageStock || productType === 'variable'">
                            <option value="instock">In Stock</option>
                            <option value="outofstock">Out of Stock</option>
                            <option value="onbackorder">On Backorder</option>
                        </select>
                        <template x-if="manageStock && productType !== 'variable'">
                            <p class="text-[11px] text-[#646970] mt-1 italic">Status is automatically managed based on stock quantity.</p>
                        </template>
                        <template x-if="productType === 'variable'">
                            <p class="text-[11px] text-[#2271b1] mt-1 italic">Overall status is automatically managed based on variation availability.</p>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Attributes Tab -->
            <div x-show="activeTab === 'attributes'" class="space-y-6">
                <div class="flex justify-between items-center border-b border-[#f0f0f1] pb-3 mb-4">
                    <h4 class="text-[14px] font-bold text-[#1d2327]">Product Attributes</h4>
                    <button type="button" @click="addAttribute()" class="wp-btn-secondary h-7 flex items-center space-x-1">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        <span>Add New Attribute</span>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <template x-for="(attr, index) in attributes" :key="index">
                        <div class="border border-[#c3c4c7] rounded shadow-sm bg-white overflow-hidden">
                            <div class="bg-[#f6f7f7] px-3 py-2 border-b border-[#c3c4c7] flex justify-between items-center">
                                <span class="text-[12px] font-bold text-[#1d2327]" x-text="attr.name || 'New Attribute'"></span>
                                <button type="button" @click="removeAttribute(index)" class="text-[#d63638] hover:text-red-700">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[12px] font-medium text-[#646970] mb-1">Name</label>
                                    <input type="text" x-model="attr.name" :name="'attributes_data['+index+'][name]'" class="wp-input w-full h-8 text-[13px]" placeholder="e.g. Color">
                                </div>
                                <div>
                                    <label class="block text-[12px] font-medium text-[#646970] mb-1">Value(s)</label>
                                    <textarea x-model="attr.values" :name="'attributes_data['+index+'][values]'" class="wp-input w-full text-[13px] p-2" rows="2" placeholder="Enter options separated by | (e.g. Red | Blue | Green)"></textarea>
                                </div>
                                <div class="md:col-span-2 flex items-center space-x-6 pt-2">
                                    <label class="flex items-center text-[12px] text-[#646970]">
                                        <input type="checkbox" x-model="attr.visible" :name="'attributes_data['+index+'][visible]'" value="1" class="mr-2 rounded-sm border-[#8c8f94]">
                                        Visible on the product page
                                    </label>
                                    <label class="flex items-center text-[12px] text-[#646970]" x-show="productType === 'variable'">
                                        <input type="checkbox" x-model="attr.variation" :name="'attributes_data['+index+'][variation]'" value="1" class="mr-2 rounded-sm border-[#8c8f94]">
                                        Used for variations
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="attributes.length === 0" class="p-10 text-center border-2 border-dashed border-[#dcdcde] rounded-lg">
                        <span class="material-symbols-outlined text-[48px] text-[#dcdcde] mb-2">list_alt</span>
                        <p class="text-[14px] text-[#646970]">Attributes let you define extra product data, such as size or color.</p>
                    </div>
                </div>
            </div>

            <!-- Variations Tab -->
            <div x-show="activeTab === 'variations'" class="space-y-6">
                <div class="flex justify-between items-center border-b border-[#f0f0f1] pb-3 mb-4">
                    <h4 class="text-[14px] font-bold text-[#1d2327]">Product Variations</h4>
                    <div class="flex items-center space-x-3">
                        <div class="flex space-x-2">
                            <select x-model="variationAction" class="wp-input h-7 py-0 text-[12px] min-w-[200px]">
                                <option value="">Add variation...</option>
                                <option value="add_manual">Add variation (manual)</option>
                                <option value="create">Create variations from all attributes</option>
                            </select>
                            <button type="button" @click="if(variationAction === 'create') generateVariations(); else if(variationAction === 'add_manual') addVariation();" class="wp-btn-secondary h-7 px-4">Go</button>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <template x-for="(variation, vIndex) in variations" :key="vIndex">
                        <div class="border border-[#c3c4c7] rounded bg-white overflow-hidden shadow-sm">
                            <div class="bg-[#f6f7f7] px-3 py-2 border-b border-[#c3c4c7] flex justify-between items-center">
                                <div class="flex flex-wrap gap-2 text-[12px] font-bold text-[#1d2327]">
                                    <template x-for="attr in attributes.filter(a => (a.variation == true) && a.name && a.values)" :key="attr.name">
                                        <div class="flex items-center space-x-1 bg-white border border-[#c3c4c7] px-2 py-0.5 rounded shadow-sm">
                                            <span x-text="attr.name + ':'"></span>
                                            <select :name="'variations['+vIndex+'][attributes]['+attr.name+']'" 
                                                    x-model="variation.attributes_data[attr.name]" 
                                                    class="border-none p-0 h-auto text-[11px] font-bold focus:ring-0 bg-transparent">
                                                <template x-for="val in attr.values.split('|').map(v => v.trim()).filter(v => v)" :key="val">
                                                    <option :value="val" x-text="val" :selected="variation.attributes_data[attr.name] == val"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="removeVariation(vIndex)" class="text-[#d63638] hover:text-red-700 flex items-center">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                            <div class="p-4 flex gap-6">
                                <!-- Variation Image Section -->
                                <div class="w-32 flex-shrink-0">
                                    <div @click="selectVariationImage(vIndex)" class="aspect-square border-2 border-dashed border-[#dcdcde] rounded bg-[#f6f7f7] flex items-center justify-center cursor-pointer overflow-hidden group relative">
                                        <template x-if="variation.image">
                                            <img :src="'/storage/' + variation.image" class="w-full h-full object-cover">
                                        </template>
                                        <template x-if="!variation.image">
                                            <div class="text-center p-2">
                                                <span class="material-symbols-outlined text-[#dcdcde] text-[32px]">image</span>
                                                <p class="text-[9px] text-[#646970] font-bold uppercase">Set Image</p>
                                            </div>
                                        </template>
                                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <span class="text-white text-[10px] font-bold">CHANGE</span>
                                        </div>
                                    </div>
                                    <input type="hidden" :name="'variations['+vIndex+'][image]'" :value="variation.image">
                                </div>

                                <!-- Variation Data Fields -->
                                <div class="flex-grow grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">Regular Price (৳)</label>
                                        <input type="number" step="0.01" x-model="variation.price" :name="'variations['+vIndex+'][price]'" class="wp-input w-full h-8 text-[13px]">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">Sale Price (৳)</label>
                                        <input type="number" step="0.01" x-model="variation.sale_price" :name="'variations['+vIndex+'][sale_price]'" :class="parseFloat(variation.sale_price) > 0 && parseFloat(variation.sale_price) >= parseFloat(variation.price) ? 'border-[#d63638]' : ''" class="wp-input w-full h-8 text-[13px]">
                                        <template x-if="parseFloat(variation.sale_price) > 0 && parseFloat(variation.sale_price) >= parseFloat(variation.price)">
                                            <p class="text-[#d63638] text-[10px] mt-1 italic">Must be less than regular price</p>
                                        </template>
                                    </div>
                                    <div>
                                         <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">SKU</label>
                                         <input type="text" x-model="variation.sku" :name="'variations['+vIndex+'][sku]'" class="wp-input w-full h-8 text-[13px]">
                                     </div>
                                     
                                     <div class="grid grid-cols-2 gap-4">
                                         <div>
                                             <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">Weight (kg)</label>
                                             <input type="number" step="0.01" x-model="variation.weight" :name="'variations['+vIndex+'][weight]'" class="wp-input w-full h-8 text-[13px]" placeholder="0.00">
                                         </div>
                                         <div>
                                             <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">Dimensions (L×W×H) (cm)</label>
                                             <div class="flex space-x-1">
                                                 <input type="number" step="0.01" x-model="variation.length" :name="'variations['+vIndex+'][length]'" class="wp-input w-full h-8 text-[12px] px-1" placeholder="L">
                                                 <input type="number" step="0.01" x-model="variation.width" :name="'variations['+vIndex+'][width]'" class="wp-input w-full h-8 text-[12px] px-1" placeholder="W">
                                                 <input type="number" step="0.01" x-model="variation.height" :name="'variations['+vIndex+'][height]'" class="wp-input w-full h-8 text-[12px] px-1" placeholder="H">
                                             </div>
                                         </div>
                                     </div>

                                     <div class="md:col-span-2 pt-2 border-t border-gray-50 space-y-3">
                                         <label class="flex items-center text-[11px] font-bold text-[#646970] uppercase tracking-wider cursor-pointer whitespace-nowrap">
                                             <input type="checkbox" x-model="variation.manage_stock" :name="'variations['+vIndex+'][manage_stock]'" value="1" class="mr-2 rounded-sm border-[#8c8f94] text-[#2271b1]">
                                             Manage stock?
                                         </label>
                                         
                                         <div x-show="!variation.manage_stock" class="max-w-[250px]">
                                             <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">Stock Status</label>
                                             <select x-model="variation.stock_status" :name="'variations['+vIndex+'][stock_status]'" class="wp-input h-8 py-0 w-full text-[13px]">
                                                 <option value="instock">In Stock</option>
                                                 <option value="outofstock">Out of Stock</option>
                                             </select>
                                         </div>
                                     </div>

                                     <!-- Stock Detail Group -->
                                     <div x-show="variation.manage_stock" class="md:col-span-2 grid grid-cols-2 gap-4 pt-3 mt-1 border-t border-gray-50">
                                         <div>
                                             <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">Stock Qty</label>
                                             <input type="number" x-model="variation.stock_quantity" :name="'variations['+vIndex+'][stock_quantity]'" class="wp-input w-full h-8 text-[13px]">
                                         </div>
                                         <div>
                                             <label class="block text-[11px] font-medium text-[#646970] mb-1 uppercase tracking-wider">Status (Auto)</label>
                                             <div class="h-8 flex items-center px-3 bg-gray-50 border border-[#c3c4c7] rounded text-[11px] font-bold"
                                                  :class="variation.stock_status === 'instock' ? 'text-emerald-600' : 'text-rose-600'">
                                                 <span x-text="variation.stock_status === 'instock' ? 'IN STOCK' : 'OUT OF STOCK'"></span>
                                             </div>
                                             <input type="hidden" :name="'variations['+vIndex+'][stock_status]'" :value="variation.stock_status">
                                         </div>
                                                                    </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="variations.length > 0" class="flex justify-end pt-6 border-t border-gray-100 mt-6">
                    <button type="button" 
                            @click="saveVariations()" 
                            :disabled="isSaving"
                            class="bg-[#2271b1] hover:bg-[#135e96] text-white px-6 py-2 rounded-sm text-[13px] font-bold flex items-center space-x-2 disabled:opacity-50 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[20px]" x-show="!isSaving">save</span>
                        <span x-show="!isSaving">Save All Variations</span>
                        <span x-show="isSaving">Saving Changes...</span>
                    </button>
                </div>

                <div x-show="variations.length === 0" class="p-10 text-center border-2 border-dashed border-[#dcdcde] rounded-lg">
                    <span class="material-symbols-outlined text-[48px] text-[#dcdcde] mb-2">layers</span>
                    <p class="text-[14px] text-[#646970]">Generate variations after adding attributes to set unique prices, SKUs, and stock for each combination.</p>
                </div>
            </div>

            <!-- Downloads Tab -->
            <div x-show="activeTab === 'downloads'" class="space-y-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-3 items-center">
                        <label class="text-[13px] font-semibold text-[#1d2327]">Downloadable Product</label>
                        <div class="col-span-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_downloadable" value="1" class="w-4 h-4 rounded border-[#c3c4c7] text-[#2271b1] focus:ring-[#2271b1]"
                                    {{ old('is_downloadable', $post->shopData->is_downloadable ?? false) ? 'checked' : '' }}>
                                <span class="text-[13px] text-[#1d2327]">This product has downloadable files</span>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 items-center">
                        <label class="text-[13px] font-semibold text-[#1d2327]">Download Expiry (days)</label>
                        <div class="col-span-2">
                            <input type="number" name="download_expiry_days" min="1" max="3650"
                                value="{{ old('download_expiry_days', $post->shopData->download_expiry_days ?? '') }}"
                                class="wp-input w-full max-w-[200px]" placeholder="e.g. 365">
                            <p class="text-[11px] text-[#646970] mt-1">Number of days the download link stays active after purchase. Leave blank for no expiry.</p>
                        </div>
                    </div>
                </div>

                @if(isset($post) && $post->exists && isset($post->shopData))
                <div class="border-t border-[#f0f0f1] pt-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-[13px] font-semibold text-[#1d2327]">Downloadable Files</p>
                        <button type="button"
                            @click="showUploadModal = true; dlFile = null; dlName = ''; dlLimit = ''; dlStatus = ''; dlStatusType = ''"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#2271b1] text-white text-[12px] font-semibold rounded hover:bg-[#135e96] transition">
                            <span class="material-symbols-outlined text-[15px]">add</span> Add File
                        </button>
                    </div>

                    {{-- File list driven by Alpine downloadFiles array --}}
                    <div class="space-y-2">
                        <template x-if="downloadFiles.length === 0">
                            <div class="flex flex-col items-center justify-center py-10 border border-dashed border-[#c3c4c7] rounded-sm text-center">
                                <span class="material-symbols-outlined text-[36px] text-[#dcdcde] mb-2">cloud_upload</span>
                                <p class="text-[13px] text-[#646970]">No files added yet. Click <strong>Add File</strong> to upload.</p>
                            </div>
                        </template>
                        <template x-for="(dl, idx) in downloadFiles" :key="dl.id">
                            <div class="flex items-center gap-3 px-4 py-3 bg-[#f6f7f7] border border-[#f0f0f1] rounded-sm">
                                <span class="material-symbols-outlined text-[#646970] text-[20px] flex-shrink-0">description</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] font-medium text-[#1d2327] truncate" x-text="dl.name"></p>
                                    <p class="text-[11px] text-[#646970] mt-0.5"
                                        x-text="(dl.file_size ? (dl.file_size/1024).toFixed(1)+' KB' : 'Unknown size') + ' · ' + (dl.download_limit ? 'Limit: '+dl.download_limit : 'Unlimited')">
                                    </p>
                                </div>
                                <button type="button"
                                    @click="falconDeleteDownload(idx, dl.delete_url)"
                                    class="p-1 text-[#646970] hover:text-[#d63638] transition flex-shrink-0" title="Delete">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
                @else
                <div class="border-t border-[#f0f0f1] pt-4">
                    <div class="flex items-center gap-3 bg-[#f0f6fc] border-l-4 border-[#0A66C2] p-4 rounded-sm">
                        <span class="material-symbols-outlined text-[#0A66C2]">info</span>
                        <p class="text-[13px] text-[#1d2327]">Save the product first, then come back to upload downloadable files.</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Upload Modal (inside x-data scope) --}}
    @if(isset($post) && $post->exists && isset($post->shopData))
    <div x-show="showUploadModal" x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50" @click="showUploadModal = false"></div>

        {{-- Modal card --}}
        <div class="relative bg-white rounded-sm shadow-2xl w-full max-w-md z-10"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-[#f0f0f1]">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#2271b1] text-[20px]">upload_file</span>
                    <h3 class="text-[15px] font-semibold text-[#1d2327]">Add Downloadable File</h3>
                </div>
                <button type="button" @click="showUploadModal = false"
                    class="text-[#646970] hover:text-[#1d2327] transition">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-5 py-5 space-y-4">
                {{-- File picker --}}
                <div>
                    <label class="block text-[12px] font-semibold text-[#1d2327] mb-1.5">File <span class="text-[#d63638]">*</span></label>
                    <div @click="selectDownloadFile()"
                        class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-sm cursor-pointer transition"
                        :class="dlFile ? 'border-[#2271b1] bg-[#f0f6fc]' : 'border-[#c3c4c7] hover:border-[#2271b1] bg-[#f6f7f7]'">
                        <template x-if="!dlFile">
                            <div class="flex flex-col items-center text-center pointer-events-none">
                                <span class="material-symbols-outlined text-[32px] text-[#c3c4c7] mb-1">perm_media</span>
                                <p class="text-[12px] text-[#646970]">Click to select from media library</p>
                            </div>
                        </template>
                        <template x-if="dlFile">
                            <div class="flex flex-col items-center text-center pointer-events-none px-4">
                                <span class="material-symbols-outlined text-[28px] text-[#2271b1] mb-1">description</span>
                                <p class="text-[13px] font-medium text-[#1d2327] truncate max-w-full" x-text="dlFile.file_name || dlFile.name || dlFile.path"></p>
                                <p class="text-[11px] text-[#646970] mt-0.5 truncate max-w-full" x-text="dlFile.path"></p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Display name --}}
                <div>
                    <label class="block text-[12px] font-semibold text-[#1d2327] mb-1.5">Display Name <span class="text-[#9ca3af] font-normal">(optional)</span></label>
                    <input type="text" x-model="dlName" placeholder="e.g. eBook PDF, Software v2.0"
                        class="wp-input w-full h-9 text-[13px]">
                    <p class="text-[11px] text-[#646970] mt-1">Customer sees this name on their downloads page.</p>
                </div>

                {{-- Download limit --}}
                <div>
                    <label class="block text-[12px] font-semibold text-[#1d2327] mb-1.5">Download Limit <span class="text-[#9ca3af] font-normal">(optional)</span></label>
                    <input type="number" x-model="dlLimit" min="1" placeholder="Leave blank for unlimited"
                        class="wp-input w-full h-9 text-[13px]">
                </div>

                {{-- Status --}}
                <p x-show="dlStatus" x-text="dlStatus"
                    :class="dlStatusType === 'error' ? 'text-[#d63638]' : 'text-emerald-600'"
                    class="text-[12px]"></p>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-[#f0f0f1] bg-[#f6f7f7]">
                <button type="button" @click="showUploadModal = false"
                    class="px-4 py-2 text-[13px] font-semibold text-[#50575e] border border-[#c3c4c7] rounded hover:border-[#999ba1] bg-white transition">
                    Cancel
                </button>
                <button type="button"
                    @click="falconUploadDownload()"
                    :disabled="dlUploading || !dlFile"
                    class="inline-flex items-center gap-2 px-4 py-2 text-[13px] font-semibold text-white bg-[#2271b1] rounded hover:bg-[#135e96] transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <template x-if="!dlUploading">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                    </template>
                    <template x-if="dlUploading">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </template>
                    <span x-text="dlUploading ? 'Adding…' : 'Add File'"></span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Short Description Card -->
<div class="wp-metabox mt-6 mb-6">
    <div class="wp-metabox-header"><span>Product Short Description</span></div>
    <div class="wp-metabox-content p-4">
        <textarea name="short_description" rows="3" class="wp-input w-full p-2" placeholder="Brief summary of the product...">{{ old('short_description', optional($post->shopData)->short_description ?? '') }}</textarea>
        <p class="text-[#646970] text-[12px] mt-2 italic">This concise summary will appear next to the product image on the single product page.</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const regPriceInput = document.getElementById('regular_price');
        const salePriceInput = document.getElementById('sale_price');
        const priceError = document.getElementById('price-error');

        function validatePrices() {
            if (!regPriceInput || !salePriceInput) return;
            const regPrice = parseFloat(regPriceInput.value) || 0;
            const salePrice = parseFloat(salePriceInput.value) || 0;

            if (salePrice > 0 && regPrice > 0 && salePrice >= regPrice) {
                priceError.classList.remove('hidden');
                salePriceInput.classList.add('border-[#d63638]');
            } else {
                priceError.classList.add('hidden');
                salePriceInput.classList.remove('border-[#d63638]');
            }
        }

        regPriceInput?.addEventListener('input', validatePrices);
        salePriceInput?.addEventListener('input', validatePrices);
        validatePrices();
    });
</script>
@endif
