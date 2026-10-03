(function () {
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[char];
        });
    }

    function createIntakeClientUuid() {
        if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') {
            return globalThis.crypto.randomUUID();
        }

        return 'intake-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function setupFundingBlock(block) {
        if (!block || block.dataset.ready === '1') {
            return;
        }

        block.dataset.ready = '1';
        const extra = block.querySelector('[data-funding-extra]');
        const supplierWrap = block.querySelector('[data-funding-supplier]');
        const methodWrap = block.querySelector('[data-funding-method]');
        const paidWrap = block.querySelector('[data-funding-paid]');

        function refreshMode() {
            const modeInput = block.querySelector('input[name="funding_mode"]:checked');
            const mode = modeInput ? modeInput.value : 'none';
            const showSupplier = mode === 'credit' || mode === 'partial' || mode === 'paid';
            const showMethod = mode === 'paid' || mode === 'partial';
            const showPaid = mode === 'partial';

            if (extra) {
                extra.classList.toggle('hidden', mode === 'none');
            }
            if (supplierWrap) {
                supplierWrap.classList.toggle('hidden', !showSupplier);
            }
            if (methodWrap) {
                methodWrap.classList.toggle('hidden', !showMethod);
            }
            if (paidWrap) {
                paidWrap.classList.toggle('hidden', !showPaid);
            }
        }

        block.addEventListener('change', function (event) {
            if (event.target.name === 'funding_mode') {
                refreshMode();
            }
        });

        refreshMode();
    }

    function lookupDuplicateBarcodes(endpoint, payload) {
        if (!endpoint || !window.SP || typeof window.SP.fetchJson !== 'function') {
            return Promise.resolve([]);
        }

        return window.SP.fetchJson(endpoint, {
            method: 'POST',
            body: JSON.stringify(payload),
        }).then(function (response) {
            return Array.isArray(response.duplicates) ? response.duplicates : [];
        });
    }

    function duplicateMessagesFromResults(duplicates) {
        return (duplicates || []).map(function (duplicate) {
            const names = Object.values(duplicate.products || {}).map(function (product) {
                return product.name;
            }).join(', ');

            return duplicate.barcode + ': ' + names;
        });
    }

    function setBusy(button, busyLabel, doneLabel) {
        if (!button) {
            return;
        }

        if (busyLabel === null) {
            button.disabled = false;
            button.textContent = doneLabel;
            return;
        }

        button.disabled = true;
        button.textContent = busyLabel;
    }

    function setElementTreeDisabled(root, disabled) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }

        root.querySelectorAll('button, input, select, textarea').forEach(function (element) {
            if (!element || typeof element !== 'object') {
                return;
            }

            if (disabled) {
                if (!element.dataset.wasDisabled) {
                    element.dataset.wasDisabled = element.disabled ? '1' : '0';
                }
                element.disabled = true;
                return;
            }

            if (element.dataset.wasDisabled === '0') {
                element.disabled = false;
            }
            delete element.dataset.wasDisabled;
        });
    }

    function setSubmitButtonsDisabled(form, disabled) {
        if (!form || typeof form.querySelectorAll !== 'function') {
            return;
        }

        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (element) {
            element.disabled = disabled;
        });
    }

    function ensureHiddenUuidInput(form) {
        if (!form || typeof form.querySelector !== 'function' || typeof form.appendChild !== 'function') {
            return '';
        }

        let input = form.querySelector('input[name="intake_client_uuid"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'intake_client_uuid';
            form.appendChild(input);
        }

        if (!input.value) {
            input.value = createIntakeClientUuid();
        }

        return input.value;
    }

    function toast(message, tone) {
        if (window.SP && typeof window.SP.toast === 'function' && message) {
            window.SP.toast(message, tone || 'info');
        }
    }

    function submitJson(url, payload, method) {
        if (!window.SP || typeof window.SP.fetchJson !== 'function') {
            return Promise.reject(new Error('fetch unavailable'));
        }

        return window.SP.fetchJson(url, {
            method: method || 'POST',
            body: JSON.stringify(payload || {}),
        });
    }

    let registered = false;

    function registerAlpineComponents() {
        if (registered || !globalThis.Alpine) {
            return;
        }

        registered = true;

        Alpine.data('productForm', function (config) {
            return {
                config: config || {},
                hasVariants: false,
                barcodeRows: [],
                variantRows: [],
                newVariantRows: [],
                duplicateMessages: [],
                profitAmount: '0.00',
                profitMargin: '0.0',
                productFormBusy: false,
                allowNativeSubmit: false,
                trackImeis: false,
                imeiSupported: true,
                imeiRows: [],
                imeiItems: [],
                imeiFilter: 'all',
                imeiLoading: false,
                imeiSaving: false,
                imeiTotal: 0,
                imeiSoldCount: 0,
                imeiUnsoldCount: 0,
                imeiSupplierId: '',
                imeiPurchasedAt: '',
                init: function () {
                    this.hasVariants = !!Number(this.config.hasVariants || 0);
                    this.trackImeis = !!Number(this.config.trackImeis || 0);
                    this.imeiSupported = this.config.imeiSupported !== false;
                    this.barcodeRows = Array.isArray(this.config.additionalBarcodes) ? this.config.additionalBarcodes.slice() : [];
                    this.variantRows = Array.isArray(this.config.variants) && this.config.variants.length ? this.config.variants.slice() : [{ name: '', quantity: '0', barcode: '' }];
                    this.newVariantRows = Array.isArray(this.config.newVariants) && this.config.newVariants.length ? this.config.newVariants.slice() : [{ name: '', quantity: '0', barcode: '' }];
                    this.imeiRows = Array.isArray(this.config.initialImeis) && this.config.initialImeis.length ? this.config.initialImeis.slice() : [''];
                    this.imeiSupplierId = this.config.initialImeiSupplierId || '';
                    this.imeiPurchasedAt = this.config.initialImeiDate || '';
                    this.updateProfitPreview();
                    if (window.SalesPointImageEditor) {
                        window.SalesPointImageEditor.mountAll(this.$root);
                    }
                    this.$nextTick(function () {
                        this.$root.querySelectorAll('[data-funding-block]').forEach(setupFundingBlock);
                    }.bind(this));

                    if (this.config.productId && this.trackImeis && this.config.imeiSupported !== false) {
                        this.loadImeis();
                    }
                },
                addBarcodeRow: function (value) {
                    this.barcodeRows.push(value || '');
                },
                removeBarcodeRow: function (index) {
                    this.barcodeRows.splice(index, 1);
                },
                addVariantRow: function () {
                    this.variantRows.push({ name: '', quantity: '0', barcode: '' });
                },
                removeVariantRow: function (index) {
                    if (this.variantRows.length > 1) {
                        this.variantRows.splice(index, 1);
                    }
                },
                addNewVariantRow: function () {
                    this.newVariantRows.push({ name: '', quantity: '0', barcode: '' });
                },
                removeNewVariantRow: function (index) {
                    if (this.newVariantRows.length > 1) {
                        this.newVariantRows.splice(index, 1);
                    }
                },
                addImeiRow: function (value) {
                    this.imeiRows.push(value || '');
                },
                removeImeiRow: function (index) {
                    if (this.imeiRows.length > 1) {
                        this.imeiRows.splice(index, 1);
                    } else {
                        this.imeiRows[0] = '';
                    }
                },
                updateProfitPreview: function () {
                    const cost = Number(this.$refs.costPrice ? this.$refs.costPrice.value : 0) || 0;
                    const selling = Number(this.$refs.sellingPrice ? this.$refs.sellingPrice.value : 0) || 0;
                    const profit = selling - cost;
                    const margin = selling > 0 ? (profit / selling) * 100 : 0;
                    this.profitAmount = profit.toFixed(2);
                    this.profitMargin = margin.toFixed(1);
                },
                runDuplicateCheck: function (options) {
                    const payload = {
                        barcode: this.$refs.mainBarcode ? this.$refs.mainBarcode.value : '',
                        additional_barcodes: this.barcodeRows,
                        ignore_product_id: this.config.productId || null,
                    };
                    const settings = options || {};

                    return lookupDuplicateBarcodes(this.config.duplicateCheckUrl, payload).then(function (duplicates) {
                        this.duplicateMessages = duplicateMessagesFromResults(duplicates);

                        if (!settings.silent) {
                            toast(
                                this.duplicateMessages.length
                                    ? this.config.strings.duplicateWarning
                                    : this.config.strings.duplicateClear,
                                this.duplicateMessages.length ? 'warning' : 'success'
                            );
                        }

                        return duplicates;
                    }.bind(this)).catch(function (error) {
                        if (!settings.silent) {
                            toast((error && error.message) || this.config.strings.duplicateLookupFailed || this.config.strings.stockError || '', 'error');
                        }
                        throw error;
                    }.bind(this));
                },
                submitProductForm: function (event) {
                    if (this.allowNativeSubmit) {
                        return true;
                    }

                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    if (this.productFormBusy) {
                        return false;
                    }

                    const form = event && event.target ? event.target : this.$root.querySelector('form[data-product-form-body]');
                    this.productFormBusy = true;
                    setSubmitButtonsDisabled(form, true);

                    return this.runDuplicateCheck({ silent: true }).then(function (duplicates) {
                        if (duplicates.length) {
                            toast(this.config.strings.duplicateWarning || '', 'warning');
                            return false;
                        }

                        this.allowNativeSubmit = true;
                        if (form && typeof form.submit === 'function') {
                            form.submit();
                        }
                        return true;
                    }.bind(this)).catch(function (error) {
                        toast((error && error.message) || this.config.strings.duplicateLookupFailed || this.config.strings.stockError || '', 'error');
                        return false;
                    }.bind(this)).finally(function () {
                        if (!this.allowNativeSubmit) {
                            this.productFormBusy = false;
                            setSubmitButtonsDisabled(form, false);
                        }
                    }.bind(this));
                },
                submitInlineStockForm: function (url) {
                    const form = this.$refs.inlineStockForm;
                    if (form && typeof form.reportValidity === 'function' && !form.reportValidity()) {
                        return;
                    }
                    this.submitJsonForm(form, url);
                },
                submitBatchUpdate: function (batchId, url, quantityRef, costRef) {
                    const formData = new FormData();
                    formData.set('quantity', quantityRef.value);
                    formData.set('cost_price', costRef.value);
                    const busyRoot = quantityRef && typeof quantityRef.closest === 'function' ? quantityRef.closest('tr') : null;
                    this.submitPayload(url, Object.fromEntries(formData.entries()), 'PUT', busyRoot);
                },
                submitBatchDelete: function (url) {
                    const busyRoot = document.activeElement && typeof document.activeElement.closest === 'function'
                        ? document.activeElement.closest('tr')
                        : null;

                    if (window.SP && typeof window.SP.confirm === 'function' && this.config.strings.deleteTitle) {
                        window.SP.confirm({
                            title: this.config.strings.deleteTitle,
                            message: this.config.strings.deleteMessage,
                            confirmText: this.config.strings.deleteConfirm,
                            danger: true,
                        }).then(function (confirmed) {
                            if (confirmed) {
                                this.submitPayload(url, {}, 'DELETE', busyRoot);
                            }
                        }.bind(this));
                        return;
                    }

                    this.submitPayload(url, {}, 'DELETE', busyRoot);
                },
                submitJsonForm: function (form, url) {
                    if (!form) {
                        return;
                    }

                    const payload = Object.fromEntries(new FormData(form).entries());
                    if (!payload.intake_client_uuid) {
                        payload.intake_client_uuid = ensureHiddenUuidInput(form);
                    }

                    this.submitPayload(url, payload, 'POST', form);
                },
                submitPayload: function (url, payload, method, busyRoot) {
                    const root = busyRoot || null;
                    if (root && root.dataset.pendingSubmit === '1') {
                        return;
                    }
                    if (root) {
                        root.dataset.pendingSubmit = '1';
                        setElementTreeDisabled(root, true);
                    }

                    submitJson(url, payload, method || 'POST').then(function () {
                        window.location.reload();
                    }).catch(function (error) {
                        if (root) {
                            delete root.dataset.pendingSubmit;
                            setElementTreeDisabled(root, false);
                        }
                        toast((error && error.message) || this.config.strings.stockError || '', 'error');
                    }.bind(this));
                },
                loadImeis: function () {
                    if (!this.config.imeiRoutes || !this.config.imeiRoutes.index || this.imeiLoading) {
                        return Promise.resolve();
                    }

                    this.imeiLoading = true;
                    const url = new URL(this.config.imeiRoutes.index, window.location.origin);
                    if (this.imeiFilter && this.imeiFilter !== 'all') {
                        url.searchParams.set('filter', this.imeiFilter);
                    }

                    return fetch(url.toString(), {
                        headers: {
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    }).then(function (response) {
                        return response.json();
                    }).then(function (payload) {
                        this.imeiItems = Array.isArray(payload.imeis) ? payload.imeis : [];
                        this.imeiTotal = Number(payload.total || this.imeiItems.length);
                        this.imeiSoldCount = Number(payload.sold_count || 0);
                        this.imeiUnsoldCount = Number(payload.unsold_count || 0);
                    }.bind(this)).catch(function () {
                        toast(this.config.strings.stockError || '', 'error');
                    }.bind(this)).finally(function () {
                        this.imeiLoading = false;
                    }.bind(this));
                },
                saveImeis: function () {
                    if (!this.config.imeiRoutes || !this.config.imeiRoutes.store || this.imeiSaving) {
                        return;
                    }

                    const imeis = this.imeiRows.map(function (value) {
                        return String(value || '').trim();
                    }).filter(Boolean);

                    if (!imeis.length) {
                        return;
                    }

                    this.imeiSaving = true;

                    submitJson(this.config.imeiRoutes.store, {
                        imeis: imeis,
                        supplier_id: this.imeiSupplierId || null,
                        purchased_at: this.imeiPurchasedAt || null,
                    }, 'POST').then(function () {
                        toast(this.config.strings.imeiSaved || '', 'success');
                        this.imeiRows = [''];
                        return this.loadImeis();
                    }.bind(this)).catch(function (error) {
                        toast((error && error.message) || this.config.strings.stockError || '', 'error');
                    }.bind(this)).finally(function () {
                        this.imeiSaving = false;
                    }.bind(this));
                },
                deleteImei: function (item) {
                    if (!item || !item.id || !this.config.imeiRoutes || !this.config.imeiRoutes.destroyBase) {
                        return;
                    }

                    const performDelete = function () {
                        submitJson(this.config.imeiRoutes.destroyBase.replace(/\/$/, '') + '/' + item.id, {}, 'DELETE').then(function () {
                            toast(this.config.strings.imeiDeleted || '', 'success');
                            return this.loadImeis();
                        }.bind(this)).catch(function (error) {
                            toast((error && error.message) || this.config.strings.stockError || '', 'error');
                        }.bind(this));
                    }.bind(this);

                    if (window.SP && typeof window.SP.confirm === 'function' && this.config.strings.deleteTitle) {
                        window.SP.confirm({
                            title: this.config.strings.deleteTitle,
                            message: this.config.strings.deleteMessage,
                            confirmText: this.config.strings.deleteConfirm,
                            danger: true,
                        }).then(function (confirmed) {
                            if (confirmed) {
                                performDelete();
                            }
                        });

                        return;
                    }

                    performDelete();
                },
            };
        });

        Alpine.data('productIndex', function (config) {
            return {
                config: config || {},
                selectedIds: [],
                stockFormOpen: false,
                stockProductId: null,
                stockProductName: '',
                stockSaving: false,
                init: function () {
                    this.$root.querySelectorAll('[data-funding-block]').forEach(setupFundingBlock);
                },
                toggleAll: function (checked) {
                    this.selectedIds = checked ? this.config.productIds.slice() : [];
                },
                openStockForm: function (product) {
                    if (this.stockSaving) {
                        return;
                    }
                    if (this.stockProductId !== product.id && this.$refs.quickStockForm) {
                        const form = this.$refs.quickStockForm;
                        form.reset();
                        form.querySelector('input[name="cost_price"]').value = product.costPrice;
                        const uuid = form.querySelector('input[name="intake_client_uuid"]');
                        if (uuid) {
                            uuid.value = '';
                        }
                        form.querySelectorAll('[data-funding-block]').forEach(function (block) {
                            const mode = block.querySelector('input[name="funding_mode"]:checked');
                            if (mode) {
                                mode.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                    }
                    this.stockProductId = product.id;
                    this.stockProductName = product.name;
                    this.stockFormOpen = true;
                    this.$nextTick(function () {
                        this.$root.querySelectorAll('[data-funding-block]').forEach(setupFundingBlock);
                    }.bind(this));
                },
                submitQuickStock: function () {
                    const form = this.$refs.quickStockForm;
                    const submit = this.$refs.quickStockSubmit;
                    if (!form || !this.stockProductId || this.stockSaving) {
                        return;
                    }
                    if (!form.reportValidity()) {
                        return;
                    }

                    const payload = Object.fromEntries(new FormData(form).entries());
                    if (!payload.intake_client_uuid) {
                        payload.intake_client_uuid = ensureHiddenUuidInput(form);
                    }
                    setBusy(submit, this.config.strings.saving, this.config.strings.submitStock);
                    setElementTreeDisabled(form, true);
                    this.stockSaving = true;

                    return submitJson(this.config.quickStockUrl.replace('__PRODUCT__', this.stockProductId), payload, 'POST').then(function () {
                        window.location.reload();
                    }).catch(function (error) {
                        this.stockSaving = false;
                        setBusy(submit, null, this.config.strings.submitStock);
                        setElementTreeDisabled(form, false);
                        toast((error && error.message) || this.config.strings.stockError, 'error');
                    }.bind(this));
                },
            };
        });

        Alpine.data('outOfStockPage', function () {
            return {
                selected: [],
                toggleAll: function (checked, ids) {
                    this.selected = checked ? ids.slice() : [];
                },
            };
        });
    }

    if (globalThis.Alpine) {
        registerAlpineComponents();
    } else {
        document.addEventListener('alpine:init', registerAlpineComponents);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-funding-block]').forEach(setupFundingBlock);
    });

    window.ProductFormHtml = {
        escape: escapeHtml,
    };

    window.ProductFormUtils = {
        createIntakeClientUuid: createIntakeClientUuid,
        duplicateMessagesFromResults: duplicateMessagesFromResults,
        setupFundingBlock: setupFundingBlock,
    };
}());
