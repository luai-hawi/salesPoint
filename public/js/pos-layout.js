(function () {
    'use strict';

    const PRESETS = ['classic', 'focus', 'visual', 'cashier', 'custom'];
    const DEFAULT_SUMMARY_WIDTH = 17;

    const presetDefaults = {
        classic: {
            preset: 'classic',
            products_width: 33,
            splitter_position: 33,
            product_card_size: 'm',
            image_aspect: 'square',
            show_image: true,
            price_badge_size: 'md',
            show_stock: true,
            show_category_badge: true,
            grid_columns: 'auto',
            category_bar: false,
            bill_side: 'end',
            summary_position: 'side',
            density: 'comfortable',
            font_scale: 100,
            kiosk_mode: false,
            quick_actions: true,
            products_tall: false,
        },
        focus: {
            preset: 'focus',
            products_width: 50,
            splitter_position: 50,
            product_card_size: 'm',
            image_aspect: 'square',
            show_image: true,
            price_badge_size: 'md',
            show_stock: true,
            show_category_badge: true,
            grid_columns: 'auto',
            category_bar: false,
            bill_side: 'end',
            summary_position: 'side',
            density: 'comfortable',
            font_scale: 100,
            kiosk_mode: false,
            quick_actions: false,
            products_tall: true,
        },
        visual: {
            preset: 'visual',
            products_width: 62,
            splitter_position: 62,
            product_card_size: 'xl',
            image_aspect: 'cover',
            show_image: true,
            price_badge_size: 'lg',
            show_stock: false,
            show_category_badge: true,
            grid_columns: 3,
            category_bar: true,
            bill_side: 'end',
            summary_position: 'under',
            density: 'comfortable',
            font_scale: 108,
            kiosk_mode: false,
            quick_actions: true,
            products_tall: true,
        },
        cashier: {
            preset: 'cashier',
            products_width: 38,
            splitter_position: 38,
            product_card_size: 's',
            image_aspect: 'square',
            show_image: false,
            price_badge_size: 'lg',
            show_stock: true,
            show_category_badge: false,
            grid_columns: 2,
            category_bar: false,
            bill_side: 'end',
            summary_position: 'side',
            density: 'compact',
            font_scale: 105,
            kiosk_mode: false,
            quick_actions: true,
            products_tall: false,
        },
    };

    function enumValue(value, allowed, fallback) {
        return allowed.indexOf(value) >= 0 ? value : fallback;
    }

    function clampInteger(value, minimum, maximum, fallback) {
        const parsed = parseInt(value, 10);
        if (Number.isNaN(parsed)) {
            return fallback;
        }

        return Math.min(maximum, Math.max(minimum, parsed));
    }

    function normalize(settings, fallbackPreset) {
        const fallback = enumValue(fallbackPreset, PRESETS, 'classic');
        const requestedPreset = enumValue((settings || {}).preset, PRESETS, fallback);
        const basePreset = requestedPreset === 'custom' ? fallback : requestedPreset;
        const base = Object.assign({}, presetDefaults[basePreset] || presetDefaults.classic);
        const input = Object.assign({}, base, settings || {}, { preset: requestedPreset });
        const gridColumns = input.grid_columns === 'auto'
            ? 'auto'
            : clampInteger(input.grid_columns, 2, 8, base.grid_columns === 'auto' ? 2 : base.grid_columns);

        const normalized = {
            preset: requestedPreset,
            products_width: clampInteger(input.products_width, 25, 75, base.products_width),
            splitter_position: clampInteger(input.splitter_position || input.products_width, 25, 75, base.splitter_position),
            product_card_size: enumValue(input.product_card_size, ['s', 'm', 'l', 'xl'], base.product_card_size),
            image_aspect: enumValue(input.image_aspect, ['square', '4:3', '16:9', 'cover'], base.image_aspect),
            show_image: !!input.show_image,
            price_badge_size: enumValue(input.price_badge_size, ['sm', 'md', 'lg'], base.price_badge_size),
            show_stock: !!input.show_stock,
            show_category_badge: !!input.show_category_badge,
            grid_columns: gridColumns,
            category_bar: !!input.category_bar,
            bill_side: enumValue(input.bill_side, ['start', 'end'], base.bill_side),
            summary_position: enumValue(input.summary_position, ['side', 'under', 'hidden'], base.summary_position),
            density: enumValue(input.density, ['comfortable', 'compact'], base.density),
            font_scale: clampInteger(input.font_scale, 90, 130, base.font_scale),
            kiosk_mode: !!input.kiosk_mode,
            quick_actions: !!input.quick_actions,
            products_tall: !!input.products_tall,
        };

        if (normalized.preset === 'classic') {
            normalized.summary_position = 'side';
            normalized.products_width = 33;
            normalized.splitter_position = 33;
            normalized.quick_actions = true;
            normalized.category_bar = false;
            normalized.products_tall = false;
        } else if (normalized.preset === 'focus') {
            normalized.summary_position = 'side';
            normalized.products_width = 50;
            normalized.splitter_position = 50;
            normalized.quick_actions = false;
            normalized.products_tall = true;
        } else if (normalized.preset === 'visual') {
            normalized.summary_position = 'under';
            normalized.products_width = Math.max(normalized.products_width, 58);
            normalized.splitter_position = normalized.products_width;
            normalized.product_card_size = 'xl';
            normalized.show_image = true;
            normalized.category_bar = true;
            normalized.products_tall = true;
            normalized.grid_columns = normalized.grid_columns === 'auto' ? 3 : Math.min(4, Math.max(2, normalized.grid_columns));
        } else if (normalized.preset === 'cashier') {
            normalized.summary_position = 'side';
            normalized.products_width = 38;
            normalized.splitter_position = 38;
            normalized.product_card_size = 's';
            normalized.show_image = false;
            normalized.density = 'compact';
            normalized.products_tall = false;
            normalized.grid_columns = normalized.grid_columns === 'auto' ? 2 : normalized.grid_columns;
        }

        return normalized;
    }

    function buildGridTemplate(widths, summaryPosition, billFirst) {
        const products = widths.products;
        const bill = widths.bill;
        const summary = widths.summary;

        if (summaryPosition === 'hidden') {
            return billFirst
                ? `minmax(min(20rem, 46%), ${bill}) minmax(10px, 12px) minmax(min(15rem, 34%), ${products})`
                : `minmax(min(15rem, 34%), ${products}) minmax(10px, 12px) minmax(min(20rem, 46%), ${bill})`;
        }

        if (summaryPosition === 'under') {
            return billFirst
                ? `minmax(min(20rem, 46%), ${bill}) minmax(10px, 12px) minmax(min(15rem, 34%), ${products})`
                : `minmax(min(15rem, 34%), ${products}) minmax(10px, 12px) minmax(min(20rem, 46%), ${bill})`;
        }

        return billFirst
            ? `minmax(min(20rem, 42%), ${bill}) minmax(10px, 12px) minmax(min(15rem, 30%), ${products}) minmax(min(10.5rem, 17%), ${summary})`
            : `minmax(min(15rem, 30%), ${products}) minmax(10px, 12px) minmax(min(20rem, 42%), ${bill}) minmax(min(10.5rem, 17%), ${summary})`;
    }

    function toCssState(settings) {
        const state = normalize(settings, settings && settings.preset === 'focus' ? 'focus' : 'classic');
        const billFirst = state.bill_side === 'start';
        const summaryWidth = state.summary_position === 'side' ? DEFAULT_SUMMARY_WIDTH : 0;
        const billWidth = Math.max(20, 100 - state.products_width - summaryWidth);

        const widths = {
            products: state.products_width + '%',
            bill: billWidth + '%',
            summary: summaryWidth + '%',
        };

        const gridTemplate = buildGridTemplate(widths, state.summary_position, billFirst);
        const productGridTemplate = state.grid_columns === 'auto'
            ? 'repeat(auto-fit, minmax(var(--pos-product-grid-min), 1fr))'
            : `repeat(${state.grid_columns}, minmax(0, 1fr))`;

        return {
            state: state,
            vars: {
                '--pos-products-width': widths.products,
                '--pos-bill-width': widths.bill,
                '--pos-summary-width': widths.summary,
                '--pos-font-scale': String(state.font_scale / 100),
                '--pos-grid-template': productGridTemplate,
                '--pos-layout-template': gridTemplate,
                '--pos-product-grid-min': state.product_card_size === 's'
                    ? '9rem'
                    : state.product_card_size === 'm'
                        ? '10.5rem'
                        : state.product_card_size === 'l'
                            ? '12rem'
                            : '14rem',
            },
            classes: {
                billFirst: billFirst,
                summaryPosition: state.summary_position,
                density: state.density,
                cardSize: state.product_card_size,
                imageAspect: state.image_aspect.replace(':', '-'),
                showImages: state.show_image,
                showStock: state.show_stock,
                showCategoryBadge: state.show_category_badge,
                quickActions: state.quick_actions,
                kioskMode: state.kiosk_mode,
                categoryBar: state.category_bar,
                preset: state.preset,
                productsTall: state.products_tall,
            },
        };
    }

    window.PosLayoutTools = {
        presetDefaults: presetDefaults,
        normalize: normalize,
        toCssState: toCssState,
    };
})();
