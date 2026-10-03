(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function createUuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (char) {
            const random = Math.random() * 16 | 0;
            const value = char === 'x' ? random : (random & 0x3 | 0x8);
            return value.toString(16);
        });
    }

    function parseTags(value) {
        if (!value) {
            return [];
        }

        return String(value)
            .split('&')
            .map(function (part) {
                return part.trim();
            })
            .filter(Boolean);
    }

    function serializeTags(tags) {
        if (!Array.isArray(tags)) {
            return '';
        }

        return tags
            .map(function (tag) {
                return String(tag || '').trim();
            })
            .filter(Boolean)
            .join('&');
    }

    function clampPaidAmount(value, total, hasCustomer, suppress) {
        if (suppress) {
            return 0;
        }

        const numericValue = Math.max(0, parseFloat(value || 0) || 0);
        if (!hasCustomer) {
            return 0;
        }

        return Math.min(numericValue, Math.max(0, parseFloat(total || 0) || 0));
    }

    window.PosCartHelpers = {
        escapeHtml: escapeHtml,
        createUuid: createUuid,
        parseTags: parseTags,
        serializeTags: serializeTags,
        clampPaidAmount: clampPaidAmount,
    };
})();
