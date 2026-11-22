{{-- Once per page with login buttons, inline styles are allowed by the content security policy of xTIPP --}}
<style>
    .plugin-oidc-button:hover { filter: brightness(1.10); cursor: pointer; }

    /* "oder" between the login form and the buttons, centered with a line on both sides */
    .plugin-oidc-separator { display: flex; align-items: center; gap: 0.75rem; }
    .plugin-oidc-separator::before,
    .plugin-oidc-separator::after { content: ""; flex: 1 1 0%; border-top: 1px solid #d1d5db; }
</style>
