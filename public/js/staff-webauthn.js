(function () {
    function decodeBase64Url(value) {
        var padded = value.replace(/-/g, '+').replace(/_/g, '/');
        while (padded.length % 4) padded += '=';
        var binary = atob(padded);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes;
    }

    function encodeBase64Url(value) {
        var bytes = value instanceof Uint8Array ? value : new Uint8Array(value);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function credentialToJson(credential) {
        if (!credential) return null;
        return {
            id: credential.id,
            rawId: encodeBase64Url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
                attestationObject: credential.response.attestationObject ? encodeBase64Url(credential.response.attestationObject) : undefined,
                authenticatorData: credential.response.authenticatorData ? encodeBase64Url(credential.response.authenticatorData) : undefined,
                signature: credential.response.signature ? encodeBase64Url(credential.response.signature) : undefined,
                userHandle: credential.response.userHandle ? encodeBase64Url(credential.response.userHandle) : undefined,
                transports: typeof credential.response.getTransports === 'function' ? credential.response.getTransports() : [],
            },
        };
    }

    async function createCredential(options) {
        if (!window.PublicKeyCredential || !navigator.credentials || typeof navigator.credentials.create !== 'function') {
            throw new Error('unsupported');
        }

        var publicKey = options.publicKey;
        publicKey.challenge = decodeBase64Url(publicKey.challenge);
        publicKey.user.id = decodeBase64Url(publicKey.user.id);
        (publicKey.excludeCredentials || []).forEach(function (item) {
            item.id = decodeBase64Url(item.id);
        });

        var credential = await navigator.credentials.create({ publicKey: publicKey });
        return credentialToJson(credential);
    }

    async function getAssertion(options) {
        if (!window.PublicKeyCredential || !navigator.credentials || typeof navigator.credentials.get !== 'function') {
            throw new Error('unsupported');
        }

        var publicKey = options.publicKey;
        publicKey.challenge = decodeBase64Url(publicKey.challenge);
        (publicKey.allowCredentials || []).forEach(function (item) {
            item.id = decodeBase64Url(item.id);
        });

        var assertion = await navigator.credentials.get({ publicKey: publicKey });
        return credentialToJson(assertion);
    }

    window.StaffWebAuthn = {
        supported: function () {
            return !!(window.PublicKeyCredential && navigator.credentials);
        },
        createCredential: createCredential,
        getAssertion: getAssertion,
    };
})();
