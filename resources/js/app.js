import './bootstrap';
import Alpine from 'alpinejs';
import { createApp } from 'vue';

import LocalizedLanguageSelector from './components/LocalizedLanguageSelector.vue';
import MobileLanguageSelector from './components/MobileLanguageSelector.vue';

// Configurar Alpine
window.Alpine = Alpine;
Alpine.start();

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    const pageClass = document.body.dataset.page;
    if (pageClass) {
        try {
            import(`../css/pages/${pageClass}.css`)
                .catch(err => console.log(`No CSS found for ${pageClass}`));
        } catch (e) {
            console.log('Error loading page-specific CSS');
        }
    }

    // Montar componente de selector de idiomas localizado
    const localizedSelectorEl = document.getElementById('localized-language-selector');
    if (localizedSelectorEl) {
        const languages = JSON.parse(localizedSelectorEl.dataset.languages || '[]');
        const currentLocale = localizedSelectorEl.dataset.currentLocale || 'es';

        createApp(LocalizedLanguageSelector, {
            languages: languages,
            currentLocale: currentLocale,
            title: localizedSelectorEl.dataset.title || "Seleccionar idioma"
        }).mount('#localized-language-selector');
    }

    // Montar componente de selector de idiomas para móvil
    const mobileSelectorEl = document.getElementById('mobile-language-selector');
    if (mobileSelectorEl) {
        const languages = JSON.parse(mobileSelectorEl.dataset.languages || '[]');
        const currentLocale = mobileSelectorEl.dataset.currentLocale || 'es';

        createApp(MobileLanguageSelector, {
            languages: languages,
            currentLocale: currentLocale,
            title: mobileSelectorEl.dataset.title || "Idioma"
        }).mount('#mobile-language-selector');
    }

    // Menú móvil
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        document.addEventListener('click', (event) => {
            if (!mobileMenuToggle.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
    }
});
