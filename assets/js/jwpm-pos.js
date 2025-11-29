/**
 * JWPM POS — Main JavaScript File
 * Merged Parts: 1 (UI), 2 (Search), 3 (Cart), 4 (Customer), 5 (Payment), 6 (Complete)
 */

(function($) {
    "use strict";

    // Global Namespace Setup
    window.JWPMPos = window.JWPMPos || {};
    window.JWPMPos.state = window.JWPMPos.state || {};
    window.JWPMPos.utils = window.JWPMPos.utils || {};

    // --- UTILS ---
    window.JWPMPos.utils.formatCurrency = function(amount) {
        return parseFloat(amount || 0).toFixed(2);
    };
    
    // Fallback for jwpmPosData if JS loads before localization
    var jwpmPosData = window.jwpmPosData || { 
        nonce: '', 
        ajax_url: ajaxurl,
        default_branch: 0 
    };

    /**
     * ========================================================================
     * PART 1: CORE INITIALIZATION & UI RENDERING
     * ========================================================================
     */
    const JWPM_POS_Core = {
        root: null,

        init() {
            this.root = document.getElementById("jwpm-pos-root");
            if (!this.root) return; // Not POS page

            this.renderLayout();
            this.startClock();
            
            // Trigger other modules initialization
            if (typeof window.JWPMPos.onReadyCallback === 'function') {
                window.JWPMPos.onReadyCallback();
            }
            
            // Dispatch event that UI is ready
            document.dispatchEvent(new Event('jwpm_pos_ui_ready'));
        },

        renderLayout() {
            // Direct HTML Injection (No templates needed)
            this.root.innerHTML = `
                <div class="jwpm-pos-wrapper" style="display:flex; height: calc(100vh - 50px); gap:15px; flex-wrap:wrap;">
                    
                    <div class="jwpm-pos-left" style="flex:1.2; background:#fff; border:1px solid #ccc; display:flex; flex-direction:column;">
                        <div style="padding:10px; border-bottom:1px solid #eee; background:#f9f9f9;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                                <strong>Date: <span class="js-pos-datetime">Loading...</span></strong>
                                <span style="color:#d63638; font-weight:bold;">Gold Rate: <span class="js-gold-rate">0.00</span></span>
                            </div>
                            <div style="display:flex; gap:5px;">
                                <input type="text" class="js-pos-search-text" placeholder="Search Item / Scan Barcode..." style="flex:1; padding:8px;">
                                <select class="js-pos-filter-category" style="width:120px;">
                                    <option value="">All Categories</option>
                                    <option value="Ring">Ring</option>
                                    <option value="Bangle">Bangle</option>
                                    <option value="Chain">Chain</option>
                                </select>
                                <select class="js-pos-filter-karat" style="width:80px;">
                                    <option value="">Karat</option>
                                    <option value="24K">24K</option>
                                    <option value="22K">22K</option>
                                    <option value="21K">21K</option>
                                    <option value="18K">18K</option>
                                </select>
                            </div>
                        </div>

                        <div class="js-pos-search-results" style="flex:1; overflow-y:auto; padding:10px;">
                            <div style="text-align:center; color:#999; margin-top:50px;">Use search to find items.</div>
                        </div>
                    </div>

                    <div class="jwpm-pos-middle" style="flex:1.5; background:#fff; border:1px solid #ccc; display:flex; flex-direction:column;">
                        <div style="padding:10px; background:#f1f1f1; border-bottom:1px solid #ccc; font-weight:bold;">
                            Current Sale
                        </div>
                        <div style="flex:1; overflow-y:auto;">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th style="width:50px;">Qty</th>
                                        <th style="width:70px;">Price</th>
                                        <th style="width:60px;">Disc.</th>
                                        <th style="width:70px;">Total</th>
                                        <th style="width:30px;">x</th>
                                    </tr>
                                </thead>
                                <tbody id="jwpm-pos-cart-body">
                                    </tbody>
                            </table>
                        </div>
                        
                        <div style="padding:15px; background:#fafafa; border-top:2px solid #333;">
                            <div style="display:flex; justify-content:space-between;">
                                <span>Subtotal:</span>
                                <span id="jwpm-pos-subtotal">0.00</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:5px;">
                                <span>Overall Discount:</span>
                                <input type="number" id="jwpm-pos-overall-discount-input" style="width:80px; text-align:right;" placeholder="0">
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:5px; display:none;">
                                <span>Old Gold Net:</span>
                                <span id="jwpm-pos-old-gold-net">0.00</span>
                            </div>
                            <hr>
                            <div style="display:flex; justify-content:space-between; font-size:1.4em; font-weight:bold; color:#0073aa;">
                                <span>Grand Total:</span>
                                <span id="jwpm-pos-grand-total">0.00</span>
                            </div>
                        </div>
                    </div>

                    <div class="jwpm-pos-right" style="flex:1; background:#fff; border:1px solid #ccc; display:flex; flex-direction:column; padding:10px; overflow-y:auto;">
                        
                        <div style="border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:15px;">
                            <h3>Customer</h3>
                            <input type="text" id="jwpm-pos-customer-search-input" placeholder="Search Customer (Phone/Name)..." style="width:100%; padding:8px;">
                            <div id="jwpm-pos-customer-results" style="max-height:100px; overflow-y:auto; border:1px solid #eee; margin-top:5px; display:none;"></div>
                            
                            <div id="jwpm-pos-customer-selected-wrapper" style="margin-top:10px; padding:10px; background:#e8f6fe; border-radius:4px; display:none;">
                                <strong>Selected:</strong> <span id="jwpm-pos-customer-name">Guest</span>
                                <br><small id="jwpm-pos-customer-phone"></small>
                                <button id="jwpm-pos-customer-clear-selected" class="button button-small" style="float:right; margin-top:-20px;">Change</button>
                                <input type="hidden" id="jwpm-pos-customer-id">
                            </div>
                        </div>

                        <div style="flex:1;">
                            <h3>Payment</h3>
                            <div style="margin-bottom:10px;">
                                <label><input type="radio" name="jwpm-pos-payment-method" value="cash" checked> Cash</label>
                                <label style="margin-left:10px;"><input type="radio" name="jwpm-pos-payment-method" value="card"> Card</label>
                                <label style="margin-left:10px;"><input type="radio" name="jwpm-pos-payment-method" value="split"> Split/Mix</label>
                            </div>

                            <table class="form-table" style="margin:0;">
                                <tr>
                                    <td>Cash Paid:</td>
                                    <td><input type="number" id="jwpm-pos-pay-cash-amount" class="widefat"></td>
                                </tr>
                                <tr>
                                    <td>Card Paid:</td>
                                    <td><input type="number" id="jwpm-pos-pay-card-amount" class="widefat"></td>
                                </tr>
                            </table>

                            <div style="margin-top:20px; text-align:center;">
                                <div style="font-size:1.1em;">Total Due: <strong id="jwpm-pos-total-due">0.00</strong></div>
                                <div style="color:green;">Paid: <span id="jwpm-pos-amount-paid">0.00</span></div>
                                <div style="color:red; font-weight:bold;">Balance: <span id="jwpm-pos-remaining-due">0.00</span></div>
                            </div>
                            
                            <div id="jwpm-pos-complete-sale-error" style="color:red; text-align:center; margin:10px 0; display:none;"></div>

                            <button id="jwpm-pos-complete-sale-btn" class="button button-primary button-hero" style="width:100%; margin-top:20px;">
                                Complete Sale ✅
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="jwpm-pos-loading-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.7);z-index:9999;display:none;justify-content:center;align-items:center;">
                    <span class="spinner is-active" style="float:none;width:40px;height:40px;"></span>
                </div>
            `;

            // Customer selected wrapper visible toggle logic handled in Customer module
            // but for CSS, lets ensure wrapper is visible initially if needed or hidden
            // Logic handled by modules below.
        },

        startClock() {
            const el = this.root.querySelector(".js-pos-datetime");
            if (!el) return;
            const update = () => {
                const now = new Date();
                el.textContent = now.toLocaleDateString() + " " + now.toLocaleTimeString();
            };
            update();
            setInterval(update, 1000);
        }
    };

    /**
     * ========================================================================
     * PART 2: PRODUCT SEARCH
     * ========================================================================
     */
    function initSearchModule() {
        const $root = $("#jwpm-pos-root");
        const $searchInput = $root.find(".js-pos-search-text");
        const $catSelect = $root.find(".js-pos-filter-category");
        const $karatSelect = $root.find(".js-pos-filter-karat");
        const $resultsHolder = $root.find(".js-pos-search-results");
        
        let searchTimer = null;

        function runSearch() {
            const keyword = $searchInput.val();
            const category = $catSelect.val();
            const karat = $karatSelect.val();

            $resultsHolder.html('<div style="padding:10px;">Searching...</div>');

            $.post(ajaxurl, {
                action: 'jwpm_pos_search_items',
                security: jwpmPosData.nonce,
                keyword: keyword,
                category: category,
                karat: karat,
                branch_id: jwpmPosData.default_branch
            }, function(res) {
                if(!res.success) {
                    $resultsHolder.html('<div style="padding:10px; color:red;">Error or No Data</div>');
                    return;
                }
                renderResults(res.data.items || []);
            });
        }

        function renderResults(items) {
            $resultsHolder.empty();
            if(!items.length) {
                $resultsHolder.html('<div style="padding:10px;">No items found.</div>');
                return;
            }

            items.forEach(item => {
                const $row = $(`
                    <div style="padding:10px; border-bottom:1px solid #eee; cursor:pointer; display:flex; justify-content:space-between; align-items:center;" class="jwpm-pos-search-item">
                        <div>
                            <strong>${item.category || 'Item'} (${item.tag_serial})</strong><br>
                            <small>${item.sku || ''} | ${item.karat || ''} | ${parseFloat(item.net_weight).toFixed(3)}g</small>
                        </div>
                        <button class="button button-small">Add</button>
                    </div>
                `);

                $row.click(() => {
                    // Trigger Event for Cart
                    document.dispatchEvent(new CustomEvent('jwpm_pos_item_selected', { detail: { item: item } }));
                });

                $resultsHolder.append($row);
            });
        }

        $searchInput.on('keyup', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runSearch, 500);
        });
        
        $catSelect.on('change', runSearch);
        $karatSelect.on('change', runSearch);
        
        // Initial empty search to populate list? Optional.
        // runSearch();
    }

    /**
     * ========================================================================
     * PART 3: CART LOGIC
     * ========================================================================
     */
    function initCartModule() {
        const cartItems = [];
        const $cartBody = $('#jwpm-pos-cart-body');
        const $subtotalEl = $('#jwpm-pos-subtotal');
        const $grandTotalEl = $('#jwpm-pos-grand-total');
        const $discountInput = $('#jwpm-pos-overall-discount-input');

        document.addEventListener('jwpm_pos_item_selected', (e) => {
            const item = e.detail.item;
            addItemToCart(item);
        });

        function addItemToCart(item) {
            // Check existing
            const existing = cartItems.find(i => i.id == item.id);
            if(existing) {
                existing.qty++;
            } else {
                cartItems.push({
                    id: item.id,
                    name: `${item.category} ${item.tag_serial}`,
                    price: 0, // In jewelry POS, price is often manual or calc from gold rate. Assuming 0 for manual entry.
                    qty: 1,
                    discount: 0,
                    total: 0
                });
            }
            renderCart();
        }

        function renderCart() {
            $cartBody.empty();
            let subtotal = 0;

            cartItems.forEach((item, index) => {
                // Calculate line total
                const lineTotal = (item.price * item.qty) - item.discount;
                item.total = lineTotal > 0 ? lineTotal : 0;
                subtotal += item.total;

                const $tr = $(`
                    <tr>
                        <td>${item.name}</td>
                        <td><input type="number" class="js-qty" data-idx="${index}" value="${item.qty}" style="width:50px;"></td>
                        <td><input type="number" class="js-price" data-idx="${index}" value="${item.price}" style="width:70px;"></td>
                        <td><input type="number" class="js-disc" data-idx="${index}" value="${item.discount}" style="width:60px;"></td>
                        <td>${item.total.toFixed(2)}</td>
                        <td><span class="js-remove" data-idx="${index}" style="color:red; cursor:pointer; font-weight:bold;">&times;</span></td>
                    </tr>
                `);
                $cartBody.append($tr);
            });

            const overallDisc = parseFloat($discountInput.val() || 0);
            const grandTotal = Math.max(0, subtotal - overallDisc);

            $subtotalEl.text(subtotal.toFixed(2));
            $grandTotalEl.text(grandTotal.toFixed(2));

            // Sync with Global State for Payment Module
            window.JWPMPos.state.cartTotals = {
                grandTotal: grandTotal,
                items: cartItems
            };
            
            // Notify Payment Module
            document.dispatchEvent(new CustomEvent('jwpm_pos_cart_totals_updated'));
        }

        // Event Delegation for inputs
        $cartBody.on('change', 'input', function() {
            const idx = $(this).data('idx');
            const val = parseFloat($(this).val() || 0);
            
            if($(this).hasClass('js-qty')) cartItems[idx].qty = val;
            if($(this).hasClass('js-price')) cartItems[idx].price = val;
            if($(this).hasClass('js-disc')) cartItems[idx].discount = val;

            renderCart();
        });

        $cartBody.on('click', '.js-remove', function() {
            const idx = $(this).data('idx');
            cartItems.splice(idx, 1);
            renderCart();
        });

        $discountInput.on('input', renderCart);
        
        // Expose clear function
        window.JWPMPos.clearCart = function() {
            cartItems.length = 0;
            $discountInput.val('');
            renderCart();
        };
    }

    /**
     * ========================================================================
     * PART 4: CUSTOMER SEARCH
     * ========================================================================
     */
    function initCustomerModule() {
        const $input = $('#jwpm-pos-customer-search-input');
        const $results = $('#jwpm-pos-customer-results');
        const $selectedWrap = $('#jwpm-pos-customer-selected-wrapper');
        const $nameEl = $('#jwpm-pos-customer-name');
        const $phoneEl = $('#jwpm-pos-customer-phone');
        const $idInput = $('#jwpm-pos-customer-id');
        const $clearBtn = $('#jwpm-pos-customer-clear-selected');

        let timer = null;

        $input.on('keyup', function() {
            const term = $(this).val();
            if(term.length < 3) { $results.hide(); return; }
            
            clearTimeout(timer);
            timer = setTimeout(() => {
                $.post(ajaxurl, {
                    action: 'jwpm_pos_search_customer',
                    security: jwpmPosData.nonce,
                    keyword: term
                }, function(res) {
                    if(res.success && res.data.customers.length) {
                        $results.empty().show();
                        res.data.customers.forEach(cust => {
                            const $div = $(`<div style="padding:5px; border-bottom:1px solid #eee; cursor:pointer;">${cust.name} (${cust.phone})</div>`);
                            $div.click(() => selectCustomer(cust));
                            $results.append($div);
                        });
                    } else {
                        $results.html('<div style="padding:5px;">No customer found.</div>').show();
                    }
                });
            }, 500);
        });

        function selectCustomer(cust) {
            $results.hide();
            $input.val(''); // Clear search
            $idInput.val(cust.id);
            $nameEl.text(cust.name);
            $phoneEl.text(cust.phone);
            
            $selectedWrap.show();
            // Notify global state
            window.JWPMPos.state.customer = cust;
        }

        $clearBtn.click(() => {
            $idInput.val('');
            $selectedWrap.hide();
            window.JWPMPos.state.customer = null;
        });
    }

    /**
     * ========================================================================
     * PART 5: PAYMENT & CALCULATIONS
     * ========================================================================
     */
    function initPaymentModule() {
        const $totalDue = $('#jwpm-pos-total-due');
        const $paidEl = $('#jwpm-pos-amount-paid');
        const $remainingEl = $('#jwpm-pos-remaining-due');
        
        const $cashInput = $('#jwpm-pos-pay-cash-amount');
        const $cardInput = $('#jwpm-pos-pay-card-amount');

        function updatePayment() {
            const totals = window.JWPMPos.state.cartTotals || { grandTotal: 0 };
            const grandTotal = totals.grandTotal;

            const cash = parseFloat($cashInput.val() || 0);
            const card = parseFloat($cardInput.val() || 0);
            const totalPaid = cash + card;
            
            const remaining = grandTotal - totalPaid;

            $totalDue.text(grandTotal.toFixed(2));
            $paidEl.text(totalPaid.toFixed(2));
            $remainingEl.text(remaining.toFixed(2));
            
            if(remaining > 0) $remainingEl.css('color', 'red');
            else $remainingEl.css('color', 'green');
        }

        // Listen for Cart Updates
        document.addEventListener('jwpm_pos_cart_totals_updated', updatePayment);
        
        // Listen for Inputs
        $cashInput.on('input', updatePayment);
        $cardInput.on('input', updatePayment);
    }

    /**
     * ========================================================================
     * PART 6: COMPLETE SALE
     * ========================================================================
     */
    function initCompleteSaleModule() {
        const $btn = $('#jwpm-pos-complete-sale-btn');
        const $error = $('#jwpm-pos-complete-sale-error');
        const $overlay = $('#jwpm-pos-loading-overlay');

        $btn.click(function() {
            $error.hide();
            const state = window.JWPMPos.state;
            const cartItems = (state.cartTotals && state.cartTotals.items) || [];
            
            if(cartItems.length === 0) {
                alert("Cart is empty!");
                return;
            }

            const grandTotal = state.cartTotals.grandTotal;
            const cash = parseFloat($('#jwpm-pos-pay-cash-amount').val() || 0);
            const card = parseFloat($('#jwpm-pos-pay-card-amount').val() || 0);
            const paid = cash + card;

            if(paid < grandTotal) {
                if(!confirm("Partial payment detected. Mark remaining as Pending?")) {
                    return;
                }
            }

            // Payload
            const saleData = {
                items: cartItems,
                customer_id: $('#jwpm-pos-customer-id').val(),
                payment: {
                    total: grandTotal,
                    cash: cash,
                    card: card,
                    method: $('input[name="jwpm-pos-payment-method"]:checked').val()
                }
            };

            $overlay.css('display', 'flex');

            $.post(ajaxurl, {
                action: 'jwpm_pos_complete_sale',
                security: jwpmPosData.nonce,
                sale_data: saleData
            }, function(res) {
                $overlay.hide();
                if(res.success) {
                    alert("Sale Completed Successfully!");
                    // Reset everything
                    if(window.JWPMPos.clearCart) window.JWPMPos.clearCart();
                    $('#jwpm-pos-customer-clear-selected').click();
                    $('#jwpm-pos-pay-cash-amount').val('');
                    $('#jwpm-pos-pay-card-amount').val('');
                } else {
                    $error.text(res.data.message || "Failed to complete sale").show();
                }
            }).fail(function() {
                $overlay.hide();
                $error.text("Server Error").show();
            });
        });
    }

    // --- BOOTSTRAP ---
    $(document).ready(function() {
        // 1. Core UI
        JWPM_POS_Core.init();

        // 2. Wait for UI to be injected, then init modules
        // Since we are synchronous here, we can call them directly after init
        initSearchModule();
        initCartModule();
        initCustomerModule();
        initPaymentModule();
        initCompleteSaleModule();
    });

})(jQuery);
