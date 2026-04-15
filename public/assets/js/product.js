// ============================================
// GLOBAL VARIABLES
// ============================================
let dbAttributes;
let variants = [];
let removedVariants = [];
let attributeCounter = 0;
let productPricing = null;
let stores = [];
let cachedStores = null;
let storesPromise = null;

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener("DOMContentLoaded", function () {
    try {
        // Initialize Categories Tree
        const categoriesElement = document.getElementById("categories");
        if (categoriesElement) {
            const categories = JSON.parse(categoriesElement.dataset.categories);
            $("#categories-tree")
                .jstree({
                    core: {
                        data: categories,
                        themes: {
                            variant: "large",
                        },
                    },
                    checkbox: {
                        keep_selected_style: true,
                    },
                    plugins: ["wholerow"],
                })
                .on("ready.jstree", function () {
                    const tree = $("#categories-tree").jstree(true);
                    if (
                        window.productData &&
                        window.productData.product &&
                        window.productData.product.category_id
                    ) {
                        tree.select_node(
                            window.productData.product.category_id.toString(),
                        );
                    }
                })
                .on("select_node.jstree", function (e, data) {
                    $("#selected_category").val(data.node.id);
                });
        }

        // Initialize Addons Tree
        initializeAddons();

        // Initialize Tom Select for Categories (if exists)
        initializeCategoryTomSelect();

        // Initialize Tom Select for Tax Groups
        initializeTaxGroupTomSelect();

        // Initialize Product Tags
        initializeProductTags();

        // Initialize Custom Fields
        initializeCustomFields();

        // Initialize Edit Mode if product data exists
        if (window.productData) {
            initializeEditMode();
        }

        // Initialize Product Type Toggle
        const productType = document.getElementById("productType");
        if (productType) {
            productType.addEventListener("change", toggleProductVariantSection);
            toggleProductVariantSection();
        }

        // Initialize Wizard Steps
        initializeWizardSteps();

        // Initialize Video Type Toggle
        initializeVideoTypeToggle();

        // Initialize Profit Calculation
        initializeProfitCalculation();

        // Initialize Form Submission
        initializeFormSubmission();
        initializeAddonFormSubmission();
    } catch (e) {
        console.error("Initialization error:", e);
    }
});

// ============================================
// ADDONS FUNCTIONALITY
// ============================================
function initializeAddons() {
    const addonSelect = document.getElementById("addon-choices");
    if (!addonSelect) return;

    const selectedElement = document.getElementById("selected-addons");
    const addonsElement = document.getElementById("addons");

    let allAddons = addonsElement
        ? JSON.parse(addonsElement.dataset.addons || "[]")
        : [];

    let selectedAddons = [];

    // =========================
    // SAFE PARSING + DEBUG
    // =========================
    try {
        const raw = selectedElement?.dataset.selected;

        console.log("RAW selected-addons:", raw);

        if (raw) {
            selectedAddons = JSON.parse(raw);

            // handle double-encoded JSON
            if (typeof selectedAddons === "string") {
                selectedAddons = JSON.parse(selectedAddons);
            }

            // force array
            if (!Array.isArray(selectedAddons)) {
                selectedAddons = Object.values(selectedAddons);
            }
        }
    } catch (e) {
        console.error("Invalid selectedAddons JSON", e);
        selectedAddons = [];
    }

    console.log("Parsed selectedAddons:", selectedAddons);

    let searchTimeout;

    // =========================
    // INIT CHOICES
    // =========================
    const choices = new Choices(addonSelect, {
        removeItemButton: true,
        searchEnabled: true,
        searchFloor: 1,
        placeholder: true,
        placeholderValue: "Search and select addons...",
        noResultsText: "No addons found",
        noChoicesText: "Start typing to search for addons",
        itemSelectText: "",
        renderSelectedChoices: "auto",
        shouldSort: false,
        searchResultLimit: 20,
    });

    // =========================
    // INITIAL LOAD
    // =========================
    if (allAddons && allAddons.length > 0) {
        choices.setChoices(
            allAddons.map((addon) => ({
                value: addon.id.toString(),
                label: addon.name,
                selected: false,
            })),
            "value",
            "label",
            false,
        );
    }

    // =========================
    // PRESELECT (EDIT MODE FIX)
    // =========================
    if (selectedAddons.length > 0) {
        const selectedIds = selectedAddons.map((a) => a.id.toString());
        window.selectedAddonIds = selectedIds;

        console.log("Selected IDs:", selectedIds);

        // inject selected addons (even if not in initial list)
        choices.setChoices(
            selectedAddons.map((addon) => ({
                value: addon.id.toString(),
                label: addon.name,
                selected: true,
            })),
            "value",
            "label",
            false,
        );

        // ensure UI reflects selection
        choices.setValue(selectedIds);
    }

    // =========================
    // LOAD ADDONS (SEARCH/API)
    // =========================
    async function loadAddons(searchTerm = "") {
        try {
            let url;

            if (!searchTerm || searchTerm.length < 2) {
                if (allAddons && allAddons.length > 0) {
                    choices.clearChoices();

                    choices.setChoices(
                        allAddons.map((addon) => ({
                            value: addon.id.toString(),
                            label: addon.name,
                            selected: false,
                        })),
                        "value",
                        "label",
                        false,
                    );

                    if (window.selectedAddonIds) {
                        choices.setValue(window.selectedAddonIds);
                    }
                } else if (searchTerm === "") {
                    url = `${base_url}/${panel}/addons/search`;
                    const response = await fetch(url);
                    const addons = await response.json();

                    choices.clearChoices();

                    choices.setChoices(
                        addons.map((addon) => ({
                            value: addon.id.toString(),
                            label: addon.name,
                            selected: false,
                        })),
                        "value",
                        "label",
                        false,
                    );

                    if (window.selectedAddonIds) {
                        choices.setValue(window.selectedAddonIds);
                    }
                }
                return;
            }

            url = `${base_url}/${panel}/addons/search?search=${encodeURIComponent(searchTerm)}`;
            const response = await fetch(url);
            const addons = await response.json();

            choices.clearChoices();

            choices.setChoices(
                addons.map((addon) => ({
                    value: addon.id.toString(),
                    label: addon.name,
                    selected: false,
                })),
                "value",
                "label",
                false,
            );

            if (window.selectedAddonIds) {
                choices.setValue(window.selectedAddonIds);
            }
        } catch (error) {
            console.error("Failed to load addons:", error);
        }
    }

    // =========================
    // SEARCH HANDLER
    // =========================
    choices.passedElement.element.addEventListener("search", function (event) {
        const searchTerm = event.detail.value;

        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            loadAddons(searchTerm);
        }, 300);
    });

    // =========================
    // FORM SUBMIT HANDLER
    // =========================
    const form = addonSelect.closest("form");
    if (form) {
        form.addEventListener("submit", function () {
            const selectedValues = choices.getValue(true);

            let hiddenInput = form.querySelector("input[name='addon_ids']");
            if (!hiddenInput) {
                hiddenInput = document.createElement("input");
                hiddenInput.type = "hidden";
                hiddenInput.name = "addon_ids";
                form.appendChild(hiddenInput);
            }

            hiddenInput.value = selectedValues.join(",");
        });
    }
}

// ============================================
// CATEGORY TOM SELECT
// ============================================
function initializeCategoryTomSelect() {
    try {
        const catEl = document.getElementById("select-category");
        if (catEl && window.TomSelect) {
            new TomSelect(catEl, {
                copyClassesToDropdown: false,
                dropdownParent: "body",
                controlInput: "<input>",
                valueField: "value",
                labelField: "text",
                searchField: "text",
                placeholder: "Search category...",
                load: function (query, callback) {
                    if (!query.length) return callback();
                    const url = `${base_url}/${panel}/categories/search?search=${encodeURIComponent(query)}`;
                    fetch(url)
                        .then((response) => response.json())
                        .then((json) => callback(json))
                        .catch(() => callback());
                },
                onChange: function (value) {
                    if (value) {
                        const tree = $("#categories-tree").jstree(true);
                        if (tree && !tree.is_selected(value)) {
                            tree.select_node(value);
                        }
                        catEl.tomselect.clear();
                    }
                },
            });
        }
    } catch (e) {
        console.error("Error initializing category Tom Select:", e);
    }
}

// ============================================
// TAX GROUP TOM SELECT
// ============================================
function initializeTaxGroupTomSelect() {
    try {
        const taxGroupEl = document.getElementById("select-tax-group");
        if (taxGroupEl && window.TomSelect) {
            new TomSelect(taxGroupEl, {
                copyClassesToDropdown: false,
                dropdownParent: "body",
                create: false,
                plugins: ["remove_button"],
            });
        }
    } catch (e) {
        console.error("Error initializing tax group Tom Select:", e);
    }
}

// ============================================
// PRODUCT TAGS
// ============================================
function initializeProductTags() {
    try {
        const tagsEl = document.querySelector(".product-tags");
        if (tagsEl && window.TomSelect) {
            new TomSelect(tagsEl, {
                create: true,
                plugins: ["remove_button"],
            });
        }
    } catch (e) {
        console.error("Error initializing product tags:", e);
    }
}

// ============================================
// CUSTOM FIELDS
// ============================================
function initializeCustomFields() {
    const container = document.getElementById("customFieldsContainer");
    const addBtn = document.getElementById("addCustomFieldBtn");
    if (!container || !addBtn) return;

    function createRow(key = "", value = "") {
        const row = document.createElement("div");
        row.className = "d-flex gap-2 align-items-center mb-2";

        const keyInput = document.createElement("input");
        keyInput.type = "text";
        keyInput.className = "form-control";
        keyInput.placeholder = "Field name (e.g., ingredients)";
        keyInput.value = key;

        const valueInput = document.createElement("input");
        valueInput.type = "text";
        valueInput.className = "form-control";
        valueInput.placeholder = "Value";
        valueInput.value = value;

        function syncNames() {
            const k = keyInput.value.trim();
            const safe =
                k ||
                `__custom_${Date.now()}_${Math.floor(Math.random() * 9999)}__`;
            valueInput.name = `custom_fields[${safe}]`;
        }

        keyInput.addEventListener("input", syncNames);
        syncNames();

        const removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.className = "btn btn-outline-danger";
        removeBtn.innerHTML = '<i class="ti ti-x"></i>';
        removeBtn.addEventListener("click", () => row.remove());

        row.appendChild(keyInput);
        row.appendChild(valueInput);
        row.appendChild(removeBtn);
        return row;
    }

    try {
        const existingJson = container.getAttribute("data-existing");
        if (existingJson) {
            const existing = JSON.parse(existingJson || "{}") || {};
            Object.keys(existing).forEach((k) => {
                container.appendChild(createRow(k, existing[k]));
            });
        }
    } catch (e) {
        console.error("Error parsing existing custom fields:", e);
    }

    addBtn.addEventListener("click", function () {
        container.appendChild(createRow());
    });
}

// ============================================
// EDIT MODE INITIALIZATION
// ============================================
function initializeEditMode() {
    if (!window.productData) return;

    const productTypeSelect = document.getElementById("productType");
    if (productTypeSelect && window.productData.type) {
        productTypeSelect.value = window.productData.type;
        toggleProductVariantSection();
    }

    if (window.productData.type === "variant" && window.productData.variants) {
        initializeVariantAttributes();
        if (window.productData.product && window.productData.product.id) {
            fetchProductPricing(window.productData.product.id);
        }
    } else if (
        window.productData.type === "simple" &&
        window.productData.variant
    ) {
        if (window.productData.product && window.productData.product.id) {
            fetchProductPricing(window.productData.product.id);
        }
    }
}

// ============================================
// VARIABLE ATTRIBUTES (for variant products)
// ============================================
function initializeVariantAttributes() {
    if (!window.productData || !window.productData.variants) return;

    const attributesElement = document.getElementById("attributes");
    if (attributesElement) {
        dbAttributes = JSON.parse(attributesElement.dataset.attributes);
    }

    const variantAttributes = new Map();

    window.productData.variants.forEach((variant) => {
        if (variant.attributes) {
            variant.attributes.forEach((attr) => {
                if (!variantAttributes.has(attr.global_attribute_id)) {
                    variantAttributes.set(attr.global_attribute_id, new Set());
                }
                variantAttributes
                    .get(attr.global_attribute_id)
                    .add(attr.global_attribute_value_id);
            });
        }
    });

    variantAttributes.forEach((values, attrId) => {
        let attrKey = null;
        for (const key in dbAttributes) {
            if (dbAttributes[key].id === attrId) {
                attrKey = key;
                break;
            }
        }

        if (attrKey) {
            const id = `attr_${++attributeCounter}`;
            const html = `
                <div class="card mb-3" data-id="${id}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">Attribute</label>
                                <select class="form-select attr-select" onchange="loadValues('${id}', this.value)">
                                    <option value="">Select Attribute</option>
                                    ${Object.keys(dbAttributes)
                                        .map(
                                            (key) =>
                                                `<option value="${key}" ${key === attrKey ? "selected" : ""}>${dbAttributes[key].name}</option>`,
                                        )
                                        .join("")}
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Values</label>
                                <select class="form-select attribute-value-select" multiple size="4" data-values="${id}">
                                    <option disabled>Select attribute first</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger me-2 p-1 delete-attribute">
                                    <i class="ti ti-trash fs-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document
                .getElementById("attributesContainer")
                .insertAdjacentHTML("beforeend", html);
            loadValues(id, attrKey);

            setTimeout(() => {
                const select = document.querySelector(`[data-values="${id}"]`);
                if (select && select.tomselect) {
                    const valueIds = Array.from(values).map((v) =>
                        v.toString(),
                    );
                    select.tomselect.setValue(valueIds);
                }
            }, 100);
        }
    });

    setTimeout(() => {
        generateVariants();
        if (window.productData.variants) {
            window.productData.variants.forEach((serverVariant) => {
                const matchingVariant = variants.find((v) => {
                    if (!serverVariant.attributes || !v.attributes)
                        return false;
                    const serverAttrs = {};
                    serverVariant.attributes.forEach((attr) => {
                        serverAttrs[attr.global_attribute_id] =
                            attr.global_attribute_value_id;
                    });
                    for (const attrId in v.attributes) {
                        if (serverAttrs[attrId] !== v.attributes[attrId]) {
                            return false;
                        }
                    }
                    return true;
                });

                if (matchingVariant) {
                    matchingVariant.db_id = serverVariant.id || null;
                    matchingVariant.title = serverVariant.title || "";
                    matchingVariant.weight = serverVariant.weight || "";
                    matchingVariant.height = serverVariant.height || "";
                    matchingVariant.breadth = serverVariant.breadth || "";
                    matchingVariant.length = serverVariant.length || "";
                    matchingVariant.image = serverVariant.image || "";
                    matchingVariant.availability =
                        serverVariant.availability || "";
                    matchingVariant.barcode = serverVariant.barcode || "";
                    matchingVariant.is_default = serverVariant.is_default || "";
                }
            });
            renderVariants();
        }
    }, 500);
}

// ============================================
// PRODUCT TYPE TOGGLE
// ============================================
function toggleProductVariantSection() {
    const productType = document.getElementById("productType");
    let value = productType?.value;
    const isVariant = value === "variant";

    if (value) {
        const variationsSection = document.getElementById("variationsSection");
        const simpleProductSection = document.getElementById(
            "simpleProductSection",
        );
        const simplePricingContainer = document.getElementById(
            "simplePricingContainer",
        );
        const variantPricingContainer = document.getElementById(
            "variantPricingContainer",
        );

        if (variationsSection)
            variationsSection.classList.toggle("d-none", !isVariant);
        if (simpleProductSection)
            simpleProductSection.classList.toggle("d-none", isVariant);
        if (simplePricingContainer)
            simplePricingContainer.classList.toggle("d-none", isVariant);
        if (variantPricingContainer)
            variantPricingContainer.classList.toggle("d-none", !isVariant);

        if (!window.productData || productPricing) {
            if (isVariant) {
                initializeVariantPricing();
            } else {
                initializeSimplePricing();
            }
        }
    } else {
        const simplePricingContainer = document.getElementById(
            "simplePricingContainer",
        );
        const variantPricingContainer = document.getElementById(
            "variantPricingContainer",
        );
        if (simplePricingContainer)
            simplePricingContainer.classList.add("d-none");
        if (variantPricingContainer)
            variantPricingContainer.classList.add("d-none");
    }
}

// ============================================
// WIZARD STEPS
// ============================================
function initializeWizardSteps() {
    const steps = document.querySelectorAll(".wizard-step");
    const tabs = document.querySelectorAll(".nav-segmented .nav-link");
    const totalSteps = steps.length;
    let currentStep = getStepFromURL() || 1;

    function updateWizard() {
        steps.forEach((step) => step.classList.add("d-none"));
        tabs.forEach((tab) => tab.classList.remove("active"));

        document
            .querySelector(`.wizard-step[data-step="${currentStep}"]`)
            ?.classList.remove("d-none");
        document
            .querySelector(`.nav-link[data-step="${currentStep}"]`)
            ?.classList.add("active");

        const prevStepBtn = document.getElementById("prevStep");
        const nextStepBtn = document.getElementById("nextStep");

        if (prevStepBtn) prevStepBtn.disabled = currentStep === 1;

        if (nextStepBtn) {
            nextStepBtn.textContent =
                currentStep === totalSteps ? "Finish" : "Next";
            nextStepBtn.type = currentStep === totalSteps ? "submit" : "button";
        }

        updateURL(currentStep);
    }

    function getStepFromURL() {
        const params = new URLSearchParams(window.location.search);
        const step = parseInt(params.get("step"));
        return !isNaN(step) && step >= 1 && step <= totalSteps ? step : null;
    }

    function updateURL(step) {
        const params = new URLSearchParams(window.location.search);
        params.set("step", step);
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, "", newUrl);
    }

    document.getElementById("prevStep")?.addEventListener("click", () => {
        if (currentStep > 1) {
            currentStep--;
            updateWizard();
        }
    });

    document.getElementById("nextStep")?.addEventListener("click", (e) => {
        if (currentStep < totalSteps) {
            currentStep++;
            updateWizard();
        } else if (currentStep === totalSteps) {
            return;
        }
        e.preventDefault();
    });

    tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            currentStep = parseInt(tab.dataset.step);
            updateWizard();
        });
    });

    updateWizard();
}

// ============================================
// VIDEO TYPE TOGGLE
// ============================================
function initializeVideoTypeToggle() {
    const videoTypeSelect = document.getElementById("videoType");
    const videoLinkDiv = document
        .querySelector('input[name="video_link"]')
        ?.closest(".mb-3");
    const videoUploadDiv = document
        .querySelector('input[name="product_video"]')
        ?.closest(".mb-3");

    function toggleVideoFields() {
        const selectedType = videoTypeSelect?.value.toLowerCase() || "";
        if (videoLinkDiv && videoUploadDiv) {
            if (selectedType === "self_hosted") {
                videoLinkDiv.style.display = "none";
                videoUploadDiv.style.display = "block";
            } else if (selectedType) {
                videoLinkDiv.style.display = "block";
                videoUploadDiv.style.display = "none";
            } else {
                videoLinkDiv.style.display = "none";
                videoUploadDiv.style.display = "none";
            }
        }
    }

    toggleVideoFields();
    videoTypeSelect?.addEventListener("change", toggleVideoFields);
}

// ============================================
// PROFIT CALCULATION
// ============================================
function initializeProfitCalculation() {
    const priceInput = document.querySelector('input[name="price"]');
    const costInput = document.querySelector('input[name="cost_per_item"]');
    const profitDisplay = document.getElementById("profit_display");

    function calculateProfit() {
        const price = parseFloat(priceInput?.value) || 0;
        const cost = parseFloat(costInput?.value) || 0;
        const profit = price - cost;

        if (profitDisplay) {
            profitDisplay.value = profit.toFixed(2);
            if (profit < 0) {
                profitDisplay.classList.add("text-danger");
                profitDisplay.classList.remove("text-success");
            } else if (profit > 0) {
                profitDisplay.classList.add("text-success");
                profitDisplay.classList.remove("text-danger");
            }
        }
    }

    if (priceInput && costInput) {
        priceInput.addEventListener("input", calculateProfit);
        costInput.addEventListener("input", calculateProfit);
        calculateProfit();
    }
}

// ============================================
// FORM SUBMISSION
// ============================================
function initializeFormSubmission() {
    const productForm = document.getElementById("product-form-submit");
    if (!productForm) return;

    productForm.addEventListener("submit", function (e) {
        e.preventDefault();
        addVariantInputsToForm();

        const action = productForm.getAttribute("action");
        const originalFormData = new FormData(productForm);
        const submitButton = productForm.querySelector('button[type="submit"]');

        submitButton.disabled = true;
        const originalButtonContent = submitButton.innerHTML;
        submitButton.innerHTML = `<div class="spinner-border text-white me-2" role="status"><span class="visually-hidden">Loading...</span></div> ${originalButtonContent}`;

        const formData = restructureFormData(originalFormData);

        const config = {
            method: "POST",
            url: action,
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
            data: formData,
        };

        axios(config)
            .then(function (response) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;

                let data = response.data;
                if (data.success === false) {
                    return Toast.fire({
                        icon: "error",
                        title: data.message,
                    });
                }

                clearValidationErrors(productForm);
                Toast.fire({
                    icon: "success",
                    title: data.message,
                });

                setTimeout(function () {
                    location.reload();
                }, 3000);
            })
            .catch(function (error) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;

                if (error.response && error.response.status === 422) {
                    const validationErrors =
                        error.response.data.data || error.response.data.errors;
                    if (validationErrors) {
                        displayValidationErrors(productForm, validationErrors);
                        const firstErrorMessage =
                            error.response.data.message ||
                            Object.values(validationErrors).flat()[0] ||
                            "Validation failed";
                        return Toast.fire({
                            icon: "error",
                            title: firstErrorMessage,
                        });
                    }
                }

                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.message
                ) {
                    return Toast.fire({
                        icon: "error",
                        title: error.response.data.message,
                    });
                } else {
                    console.error("Error:", error);
                    return Toast.fire({
                        icon: "error",
                        title: "An error occurred while submitting the form.",
                    });
                }
            });
    });
}

function initializeAddonFormSubmission() {
    const form = document.getElementById("addon-form-submit");
    if (!form) return;

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const action = form.getAttribute("action");
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');

        submitButton.disabled = true;
        const originalButtonContent = submitButton.innerHTML;

        submitButton.innerHTML = `
            <div class="spinner-border text-white me-2" role="status"></div>
            ${originalButtonContent}
        `;

        axios({
            method: "POST",
            url: action,
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
            data: formData,
        })
            .then(function (response) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;

                let data = response.data;

                if (!data.success) {
                    return Toast.fire({
                        icon: "error",
                        title: data.message,
                    });
                }

                clearValidationErrors(form);

                Toast.fire({
                    icon: "success",
                    title: data.message,
                });

                setTimeout(function () {
                    window.location.href = "/seller/addons"; // better than reload
                }, 1500);
            })
            .catch(function (error) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonContent;

                if (error.response && error.response.status === 422) {
                    const validationErrors =
                        error.response.data.data || error.response.data.errors;

                    if (validationErrors) {
                        displayValidationErrors(form, validationErrors);

                        return Toast.fire({
                            icon: "error",
                            title:
                                error.response.data.message ||
                                Object.values(validationErrors).flat()[0],
                        });
                    }
                }

                return Toast.fire({
                    icon: "error",
                    title:
                        error.response?.data?.message || "Something went wrong",
                });
            });
    });
}

// ============================================
// VARIANT FUNCTIONS
// ============================================
function addAttribute() {
    const id = `attr_${++attributeCounter}`;
    const html = `
        <div class="card mb-3" data-id="${id}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Attribute</label>
                        <select class="form-select attr-select" onchange="loadValues('${id}', this.value)">
                            <option value="">Select Attribute</option>
                            ${Object.keys(dbAttributes)
                                .map(
                                    (key) =>
                                        `<option value="${key}">${dbAttributes[key].name}</option>`,
                                )
                                .join("")}
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Values</label>
                        <select class="form-select attribute-value-select" multiple size="4" data-values="${id}">
                            <option disabled>Select attribute first</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-outline-danger me-2 p-1 delete-attribute">
                            <i class="ti ti-trash fs-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document
        .getElementById("attributesContainer")
        .insertAdjacentHTML("beforeend", html);
    updateGenerateButton();
    updateAttributeOptions();
}

function loadValues(id, attrName) {
    const select = document.querySelector(`[data-values="${id}"]`);
    if (!attrName) {
        select.innerHTML = "<option disabled>Select attribute first</option>";
        updateGenerateButton();
        updateAttributeOptions();
        return;
    }

    select.innerHTML = dbAttributes[attrName].values
        .map((val) => `<option value="${val.id}">${val.name}</option>`)
        .join("");
    select.onchange = updateGenerateButton;
    updateGenerateButton();
    updateAttributeOptions();

    if (!select.tomselect) {
        new TomSelect(select, { create: false });
    } else {
        select.tomselect.clearOptions();
        dbAttributes[attrName].values.forEach((val) => {
            select.tomselect.addOption({ value: val.id, text: val.name });
        });
        select.tomselect.refreshOptions(false);
    }
}

function updateGenerateButton() {
    const attrs = getAttributes();
    const generateBtn = document.getElementById("generateVariantsBtn");
    if (generateBtn) {
        generateBtn.disabled =
            !attrs.length || !attrs.every((a) => a.values.length);
    }
}

function updateAttributeOptions() {
    const selectedAttributes = Array.from(
        document.querySelectorAll(".attr-select"),
    )
        .map((select) => select.value)
        .filter((value) => value);

    document.querySelectorAll(".attr-select").forEach((select) => {
        const currentValue = select.value;
        select.innerHTML = `
            <option value="">Select Attribute</option>
            ${Object.keys(dbAttributes)
                .map((attr) => {
                    const isDisabled =
                        selectedAttributes.includes(attr) &&
                        attr !== currentValue;
                    return `<option value="${attr}" ${isDisabled ? "disabled" : ""} ${attr === currentValue ? "selected" : ""}>${attr}</option>`;
                })
                .join("")}
        `;
    });
}

function getAttributes() {
    return Array.from(document.querySelectorAll("#attributesContainer .card"))
        .map((card) => {
            const attrKey = card.querySelector(".attr-select").value;
            if (!attrKey) return null;
            const attr = dbAttributes[attrKey];
            const values = Array.from(
                card.querySelector("[data-values]").selectedOptions,
            ).map((opt) => parseInt(opt.value));
            return attr && values.length
                ? { id: attr.id, key: attrKey, values }
                : null;
        })
        .filter(Boolean);
}

function generateCombinations(attrs) {
    return attrs.reduce(
        (acc, attr) =>
            acc.flatMap((combo) =>
                attr.values.map((val) => ({
                    ...combo,
                    [attr.id]: val,
                })),
            ),
        [{}],
    );
}

function generateVariants() {
    const attrs = getAttributes();
    const newCombinations = generateCombinations(attrs);
    removedVariants = [];

    const existingVariants = new Map();
    variants.forEach((variant) => {
        const key = JSON.stringify(variant.attributes);
        existingVariants.set(key, variant);
    });

    variants = newCombinations.map((combo, i) => {
        const key = JSON.stringify(combo);
        const existing = existingVariants.get(key);

        if (existing) {
            return existing;
        } else {
            return {
                id: `v_${Date.now()}_${i}`,
                db_id: null,
                attributes: combo,
                title: "",
                weight: "",
                height: "",
                breadth: "",
                length: "",
                availability: "",
                barcode: "",
                is_default: "",
            };
        }
    });

    renderVariants();
    const variantsContainer = document.getElementById("variantsContainer");
    if (variantsContainer) variantsContainer.classList.remove("d-none");
    updateVariantPricing();
}

function renderVariants() {
    const attrIdMap = {};
    Object.keys(dbAttributes).forEach((attrKey) => {
        const attr = dbAttributes[attrKey];
        attrIdMap[attr.id] = {
            name: attr.name,
            values: Object.fromEntries(attr.values.map((v) => [v.id, v.name])),
        };
    });

    const variantsList = document.getElementById("variantsList");
    if (!variantsList) return;

    variantsList.innerHTML = variants
        .map(
            (v) => `
        <div class="col-md-6" data-id="${v.id}">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">
                            ${v.db_id ? `<span class="badge">#${v.db_id}</span>` : ""}
                            ${Object.entries(v.attributes)
                                .map(([attrId, valueId]) => {
                                    const attr = attrIdMap[attrId];
                                    const attrName = attr ? attr.name : attrId;
                                    const valueName =
                                        attr && attr.values[valueId]
                                            ? attr.values[valueId]
                                            : valueId;
                                    const options = [
                                        "bg-primary-lt",
                                        "bg-teal-lt",
                                        "bg-warning-lt",
                                    ];
                                    const randomIndex = Math.floor(
                                        Math.random() * options.length,
                                    );
                                    return `<span class="badge ${options[randomIndex]} me-1">${attrName}: ${valueName}</span>`;
                                })
                                .join("")}
                        </h6>
                        <button type="button" class="btn btn-outline-danger btn-sm p-1" onclick="removeVariant('${v.id}')">
                            <i class="ti ti-trash fs-2"></i>
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" min="0" value="${v.title}" onchange="updateVariant('${v.id}', 'title', this.value)">
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Weight (kg)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="0" value="${v.weight}" onchange="updateVariant('${v.id}', 'weight', this.value)">
                                <span class="input-group-text">kg</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Height (cm)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="0" value="${v.height}" onchange="updateVariant('${v.id}', 'height', this.value)">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Breadth (cm)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="0" value="${v.breadth}" onchange="updateVariant('${v.id}', 'breadth', this.value)">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label required">Length (cm)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" min="0" value="${v.length}" onchange="updateVariant('${v.id}', 'length', this.value)">
                                <span class="input-group-text">cm</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Availability</label>
                            <select class="form-select" onchange="updateVariant('${v.id}', 'availability', this.value)">
                                <option value="" ${v.availability === "" ? "selected" : ""}>Select</option>
                                <option value="yes" ${v.availability == 1 ? "selected" : ""}>Yes</option>
                                <option value="no" ${v.availability == 0 ? "selected" : ""}>No</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" value="${v.barcode}" onchange="updateVariant('${v.id}', 'barcode', this.value)">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" name='is_defaults' type="radio" id="flexRadioDefault${v.id}" onchange="updateVariant('${v.id}', 'is_default', this.checked ? '1' : '0')" ${v.is_default === true || v.is_default === "1" ? "checked" : ""}>
                                <label class="form-check-label" for="flexRadioDefault${v.id}">Set as Default</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `,
        )
        .join("");
}

function updateVariant(id, field, value) {
    const variant = variants.find((v) => v.id === id);
    if (variant) variant[field] = value;
}

function removeVariant(id) {
    const index = variants.findIndex((v) => v.id === id);
    if (index > -1) {
        removedVariants.push(variants.splice(index, 1)[0]);
        document.querySelector(`div[data-id="${id}"]`)?.remove();
        const addRemovedBtn = document.getElementById("addRemovedVariantBtn");
        if (addRemovedBtn) addRemovedBtn.disabled = false;
        updateVariantPricing();
    }
}

function addVariantInputsToForm() {
    document
        .querySelectorAll(".variant-hidden-input")
        .forEach((el) => el.remove());
    const form = document.querySelector("#product-form-submit");
    if (!form) return;

    const simplifiedVariants = variants.map((variant) => ({
        id: variant.id,
        title: variant.title || "",
        weight: variant.weight || "",
        breadth: variant.breadth || "",
        length: variant.length || "",
        height: variant.height || "",
        availability: variant.availability || "",
        barcode: variant.barcode || "",
        is_default: variant.is_default || "",
        attributes: Object.entries(variant.attributes).map(
            ([attrId, valueId]) => ({
                attribute_id: attrId,
                value_id: valueId,
            }),
        ),
    }));

    const input = document.createElement("input");
    input.type = "hidden";
    input.className = "variant-hidden-input";
    input.name = "variants_json";
    input.value = JSON.stringify(simplifiedVariants);
    form.appendChild(input);
}

// ============================================
// STORE PRICING FUNCTIONS
// ============================================
function fetchStores() {
    if (cachedStores !== null) {
        return Promise.resolve(cachedStores);
    }
    if (storesPromise !== null) {
        return storesPromise;
    }
    storesPromise = axios
        .get(`${base_url}/${panel}/stores/list`)
        .then((response) => {
            cachedStores = response.data.data;
            storesPromise = null;
            return cachedStores;
        })
        .catch((error) => {
            console.error("Error fetching stores:", error);
            storesPromise = null;
            return [];
        });
    return storesPromise;
}

function fetchProductPricing(productId) {
    return axios
        .get(`${base_url}/${panel}/products/${productId}/pricing`)
        .then((response) => {
            if (response.data.success) {
                productPricing = response.data.data;
                const productType = document.getElementById("productType");
                if (productType && productType.value === "variant") {
                    updateVariantPricing();
                } else {
                    initializeSimplePricing();
                }
                return productPricing;
            }
            return null;
        })
        .catch((error) => {
            console.error("Error fetching product pricing:", error);
            return null;
        });
}

function initializeSimplePricing() {
    const container = document.getElementById("simplePricingContainer");
    if (!container) return;

    container.innerHTML =
        '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading stores...</p></div>';

    const accordionContainer = document.createElement("div");
    accordionContainer.className =
        "accordion accordion-flush border m-2 rounded";
    accordionContainer.id = "simplePricingAccordion";

    fetchStores().then((stores) => {
        if (!stores || stores.length === 0) {
            container.innerHTML =
                '<div class="alert alert-info">No stores available for pricing.</div>';
            return;
        }

        let html = "";
        stores.forEach((store, index) => {
            let storePrice = "",
                storeSpecialPrice = "",
                storeCost = "",
                storeStock = "",
                storeSku = "";

            if (productPricing && productPricing.variant_pricing) {
                const variantId =
                    window.productData && window.productData.variant
                        ? window.productData.variant.id
                        : null;
                if (variantId && productPricing.variant_pricing[variantId]) {
                    const storePricing = productPricing.variant_pricing[
                        variantId
                    ].store_pricing.find((sp) => sp.store_id === store.id);
                    if (storePricing) {
                        storePrice = storePricing.price || "";
                        storeSpecialPrice = storePricing.special_price || "";
                        storeCost = storePricing.cost || "";
                        storeStock = storePricing.stock || "";
                        storeSku = storePricing.sku || "";
                    }
                }
            }

            html += `
                <div class="accordion-item store-pricing-card" data-store-id="${store.id}">
                    <h2 class="accordion-header bg-body-tertiary">
                        <button class="accordion-button d-flex align-items-center ${index === 0 ? "" : "collapsed"}" type="button" data-bs-toggle="collapse" data-bs-target="#simple-store-${store.id}" aria-expanded="${index === 0 ? "true" : "false"}" aria-controls="simple-store-${store.id}">
                            <span class="fw-medium">${store.name}</span>
                            <button type="button" class="btn btn-outline-danger btn-icon btn-sm remove-store-pricing me-3">
                                <i class="ti ti-trash fs-2 p-1"></i>
                            </button>
                        </button>
                    </h2>
                    <div id="simple-store-${store.id}" class="accordion-collapse collapse ${index === 0 ? "show" : ""}" data-bs-parent="#simplePricingAccordion">
                        <div class="accordion-body p-2">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Price</th>
                                            <th>Special Price</th>
                                            <th>Cost</th>
                                            <th>Stock</th>
                                            <th>SKU</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">${currencySymbol}</span>
                                                    <input type="number" class="form-control store-price" name="store_pricing[${store.id}][price]" step="0.01" min="0" value="${storePrice}">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">${currencySymbol}</span>
                                                    <input type="number" class="form-control store-special-price" name="store_pricing[${store.id}][special_price]" step="0.01" min="0" value="${storeSpecialPrice}">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">${currencySymbol}</span>
                                                    <input type="number" class="form-control store-cost" name="store_pricing[${store.id}][cost]" step="0.01" min="0" value="${storeCost}">
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm store-stock" name="store_pricing[${store.id}][stock]" min="0" value="${storeStock}">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm store-sku" name="store_pricing[${store.id}][sku]" value="${storeSku}">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        accordionContainer.innerHTML = html;
        container.innerHTML = "";
        container.appendChild(accordionContainer);

        document
            .querySelectorAll(".remove-store-pricing")
            .forEach((element) => {
                element.addEventListener("click", function (e) {
                    e.stopPropagation();
                    e.target.closest(".store-pricing-card")?.remove();
                });
            });
    });
}

function initializeVariantPricing() {
    const container = document.getElementById("storePricingAccordion");
    if (!container) return;
    container.innerHTML =
        '<div class="alert alert-info">Please generate variants first to set store-specific pricing.</div>';
    if (variants.length > 0) {
        updateVariantPricing();
    }
}

function updateVariantPricing() {
    const container = document.getElementById("storePricingAccordion");
    if (!container) return;

    fetchStores().then((stores) => {
        if (!stores || stores.length === 0 || variants.length === 0) {
            container.innerHTML =
                '<div class="alert alert-info m-3">No stores or variants available for pricing.</div>';
            return;
        }

        const attrIdMap = {};
        Object.keys(dbAttributes || {}).forEach((attrKey) => {
            const attr = dbAttributes[attrKey];
            if (attr) {
                attrIdMap[attr.id] = {
                    name: attr.name,
                    values: Object.fromEntries(
                        attr.values.map((v) => [v.id, v.name]),
                    ),
                };
            }
        });

        let html = "";
        stores.forEach((store, index) => {
            html += `
                <div class="accordion-item store-pricing-card" data-store-id="${store.id}">
                    <h2 class="accordion-header bg-body-tertiary">
                        <button class="accordion-button d-flex align-items-center ${index === 0 ? "" : "collapsed"}" type="button" data-bs-toggle="collapse" data-bs-target="#store-${store.id}" aria-expanded="${index === 0 ? "true" : "false"}" aria-controls="store-${store.id}">
                            <span class="fw-medium">${store.name}</span>
                            <button type="button" class="btn btn-outline-danger btn-icon btn-sm remove-store-pricing me-3">
                                <i class="ti ti-trash fs-2 p-1"></i>
                            </button>
                        </button>
                    </h2>
                    <div id="store-${store.id}" class="accordion-collapse collapse ${index === 0 ? "show" : ""}" data-bs-parent="#storePricingAccordion">
                        <div class="accordion-body p-2">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Variant</th>
                                            <th>Price</th>
                                            <th>Special Price</th>
                                            <th>Cost</th>
                                            <th>Stock</th>
                                            <th>SKU</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${variants
                                            .map((variant) => {
                                                let storePrice = "",
                                                    storeSpecialPrice = "",
                                                    storeCost = "",
                                                    storeStock = "",
                                                    storeSku = "";

                                                if (
                                                    productPricing &&
                                                    productPricing.variant_pricing
                                                ) {
                                                    const matchingServerVariant =
                                                        window.productData?.variants?.find(
                                                            (sv) => {
                                                                if (
                                                                    !sv.attributes ||
                                                                    !variant.attributes
                                                                )
                                                                    return false;
                                                                const serverAttrs =
                                                                    {};
                                                                sv.attributes.forEach(
                                                                    (attr) => {
                                                                        serverAttrs[
                                                                            attr.global_attribute_id
                                                                        ] =
                                                                            attr.global_attribute_value_id;
                                                                    },
                                                                );
                                                                for (const attrId in variant.attributes) {
                                                                    if (
                                                                        serverAttrs[
                                                                            attrId
                                                                        ] !==
                                                                        variant
                                                                            .attributes[
                                                                            attrId
                                                                        ]
                                                                    )
                                                                        return false;
                                                                }
                                                                return true;
                                                            },
                                                        );

                                                    if (
                                                        matchingServerVariant &&
                                                        matchingServerVariant.id &&
                                                        productPricing
                                                            .variant_pricing[
                                                            matchingServerVariant
                                                                .id
                                                        ]
                                                    ) {
                                                        const storePricing =
                                                            productPricing.variant_pricing[
                                                                matchingServerVariant
                                                                    .id
                                                            ].store_pricing.find(
                                                                (sp) =>
                                                                    sp.store_id ===
                                                                    store.id,
                                                            );
                                                        if (storePricing) {
                                                            storePrice =
                                                                storePricing.price ||
                                                                "";
                                                            storeSpecialPrice =
                                                                storePricing.special_price ||
                                                                "";
                                                            storeCost =
                                                                storePricing.cost ||
                                                                "";
                                                            storeStock =
                                                                storePricing.stock ||
                                                                "";
                                                            storeSku =
                                                                storePricing.sku ||
                                                                "";
                                                        }
                                                    }
                                                }

                                                return `
                                                <tr>
                                                    <td>
                                                        ${Object.entries(
                                                            variant.attributes,
                                                        )
                                                            .map(
                                                                ([
                                                                    attrId,
                                                                    valueId,
                                                                ]) => {
                                                                    const attr =
                                                                        attrIdMap[
                                                                            attrId
                                                                        ];
                                                                    const attrName =
                                                                        attr
                                                                            ? attr.name
                                                                            : attrId;
                                                                    const valueName =
                                                                        attr &&
                                                                        attr
                                                                            .values[
                                                                            valueId
                                                                        ]
                                                                            ? attr
                                                                                  .values[
                                                                                  valueId
                                                                              ]
                                                                            : valueId;
                                                                    return `<span class="badge bg-primary-subtle text-primary me-1">${attrName}: ${valueName}</span>`;
                                                                },
                                                            )
                                                            .join("")}
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">${currencySymbol}</span>
                                                            <input type="number" class="form-control store-price" name="variant_pricing[${store.id}][${variant.id}][price]" step="0.01" min="0" value="${storePrice}">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">${currencySymbol}</span>
                                                            <input type="number" class="form-control store-special-price" name="variant_pricing[${store.id}][${variant.id}][special_price]" step="0.01" min="0" value="${storeSpecialPrice}">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">${currencySymbol}</span>
                                                            <input type="number" class="form-control store-cost" name="variant_pricing[${store.id}][${variant.id}][cost]" step="0.01" min="0" value="${storeCost}">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm store-stock" name="variant_pricing[${store.id}][${variant.id}][stock]" min="0" value="${storeStock}">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm store-sku" name="variant_pricing[${store.id}][${variant.id}][sku]" value="${storeSku}">
                                                    </td>
                                                </tr>
                                            `;
                                            })
                                            .join("")}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
        document
            .querySelectorAll(".remove-store-pricing")
            .forEach((element) => {
                element.addEventListener("click", function (e) {
                    e.stopPropagation();
                    e.target.closest(".store-pricing-card")?.remove();
                });
            });
    });
}

// ============================================
// FORM DATA RESTRUCTURING
// ============================================
function restructureFormData(originalFormData) {
    const newFormData = new FormData();
    const storePricingTemp = {};
    const variantPricingTemp = {};

    for (let [key, value] of originalFormData.entries()) {
        if (key.startsWith("store_pricing[")) {
            const matches = key.match(/store_pricing\[(\d+)\]\[([^\]]+)\]/);
            if (matches) {
                const storeId = matches[1];
                const field = matches[2];
                if (!storePricingTemp[storeId]) {
                    storePricingTemp[storeId] = { store_id: storeId };
                }
                storePricingTemp[storeId][field] = value;
            }
        } else if (key.startsWith("variant_pricing[")) {
            const matches = key.match(
                /variant_pricing\[(\d+)\]\[([^\]]+)\]\[([^\]]+)\]/,
            );
            if (matches) {
                const storeId = matches[1];
                const variantId = matches[2];
                const field = matches[3];
                const mapKey = `${storeId}_${variantId}`;
                if (!variantPricingTemp[mapKey]) {
                    variantPricingTemp[mapKey] = {
                        store_id: storeId,
                        variant_id: variantId,
                    };
                }
                variantPricingTemp[mapKey][field] = value;
            }
        } else {
            newFormData.append(key, value);
        }
    }

    const storePricing = Object.values(storePricingTemp);
    const variantPricing = Object.values(variantPricingTemp);

    newFormData.append(
        "pricing",
        JSON.stringify({
            store_pricing: storePricing,
            variant_pricing: variantPricing,
        }),
    );

    return newFormData;
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function clearValidationErrors(form) {
    form.querySelectorAll(".is-invalid").forEach((el) =>
        el.classList.remove("is-invalid"),
    );
    form.querySelectorAll(".invalid-feedback").forEach((el) => el.remove());
}

function displayValidationErrors(form, errors) {
    clearValidationErrors(form);

    for (const [field, messages] of Object.entries(errors)) {
        const input = form.querySelector(`[name="${field}"]`);
        if (input) {
            input.classList.add("is-invalid");
            const feedback = document.createElement("div");
            feedback.className = "invalid-feedback";
            feedback.textContent = Array.isArray(messages)
                ? messages[0]
                : messages;
            input.parentNode.appendChild(feedback);
        }
    }
}

// Make functions globally available
window.loadValues = loadValues;
window.updateVariant = updateVariant;
window.removeVariant = removeVariant;
window.addAttribute = addAttribute;
window.generateVariants = generateVariants;
window.removeAllVariants = function () {
    Swal.fire({
        title: "Are you sure?",
        html: "You are about to remove all variants. You can add them back from the removed variants section.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, Remove All!",
    }).then((result) => {
        if (result.isConfirmed) {
            removedVariants.push(...variants);
            variants = [];
            document.getElementById("variantsList").innerHTML = "";
            document.getElementById("addRemovedVariantBtn").disabled = false;
            updateVariantPricing();
        }
    });
};

window.showRemovedVariantsModal = function () {
    const removedVariantsList = document.getElementById("removedVariantsList");
    if (!removedVariantsList) return;

    removedVariantsList.innerHTML = removedVariants
        .map(
            (v) => `
        <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
            <div>
                <strong>
                    ${Object.entries(v.attributes)
                        .map(([attrId, valueId]) => {
                            const attr = attrIdMap[attrId];
                            const attrName = attr ? attr.name : attrId;
                            const valueName =
                                attr && attr.values[valueId]
                                    ? attr.values[valueId]
                                    : valueId;
                            return `${attrName}: ${valueName}`;
                        })
                        .join(", ")}
                </strong><br>
            </div>
            <button type="button" class="btn btn-success btn-sm" onclick="window.restoreVariant('${v.id}')">
                <i class="fas fa-plus me-1"></i>Add Back
            </button>
        </div>
    `,
        )
        .join("");

    if (removedVariants.length === 0) {
        $("#addRemovedVariantModal").modal("hide");
    } else {
        $("#addRemovedVariantModal").modal("show");
    }
};

window.restoreVariant = function (id) {
    const index = removedVariants.findIndex((v) => v.id === id);
    if (index > -1) {
        variants.push(removedVariants.splice(index, 1)[0]);
        renderVariants();
        document.getElementById("addRemovedVariantBtn").disabled =
            !removedVariants.length;
        updateVariantPricing();
        showRemovedVariantsModal();
    }
};
