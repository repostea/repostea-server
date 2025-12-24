<template>
    <div class="relative" ref="languageDropdownRef">
        <button
            @click="open = !open"
            class="flex items-center hover:text-blue-200 transition px-3 py-1 rounded-md"
            :class="{'bg-blue-700': open}"
        >
            <span class="sr-only">{{ title }}</span>
            <i class="fas fa-globe"></i>
        </button>

        <div
            v-if="open"
            class="absolute left-auto right-0 lg:left-0 lg:right-auto mt-2 w-48 rounded-lg shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20"
            style="min-width: 200px;"
        >
            <a
                v-for="language in filteredLanguages"
                :key="language.code"
                :href="cleanUrl(language.url)"
                class="block w-full text-left px-4 py-2 text-sm text-gray-800 hover:bg-gray-100 overflow-hidden"
                :class="{'bg-gray-100': currentLocale === language.code}"
            >
                <div class="flex items-center">
                    <span class="w-6 font-semibold mr-2">{{ language.code.toUpperCase() }}</span>
                    <span class="whitespace-nowrap">{{ language.native }}</span>
                </div>
            </a>
        </div>
    </div>
</template>

<script>
export default {
    name: 'LocalizedLanguageSelector',
    props: {
        languages: {
            type: Array,
            required: true
        },
        currentLocale: {
            type: String,
            required: true
        },
        title: {
            type: String,
            default: 'Seleccionar idioma'
        }
    },
    data() {
        return {
            open: false,
            supportedLocales: [
                'es', 'ca', 'eu', 'gl'
            ]
        }
    },
    computed: {
        filteredLanguages() {
            return this.languages.filter(lang => this.supportedLocales.includes(lang.code));
        }
    },
    mounted() {
        document.addEventListener('keydown', this.handleEscKey);
        document.addEventListener('click', this.handleClickOutside);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.handleEscKey);
        document.removeEventListener('click', this.handleClickOutside);
    },
    methods: {
        handleEscKey(event) {
            if (event.key === 'Escape' && this.open) {
                this.open = false;
            }
        },
        handleClickOutside(event) {
            if (this.open && this.$el && !this.$el.contains(event.target)) {
                this.open = false;
            }
        },
        cleanUrl(url) {
            try {
                const urlObj = new URL(url);
                return urlObj.pathname;
            } catch (e) {
                return url.split('?')[0];
            }
        }
    }
}
</script>
