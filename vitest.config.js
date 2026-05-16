const { defineConfig } = require('vitest/config');

// CommonJS config so Node can `require()` it without the `"type": "module"`
// switch (we keep the package CJS so `webroot/js/designer-helpers.js` can
// also be `require()`d directly by tests). Test execution itself happens in
// CI (M4-T19); the dev Docker image is PHP-only so we never run vitest here.
module.exports = defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
        environment: 'node',
    },
});
