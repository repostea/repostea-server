<template>
    <div class="language-selector-mobile">
        <div @click="showModal = true" class="flex items-center py-2 text-white hover:text-blue-200 cursor-pointer">
            <i class="fas fa-globe mr-2"></i>
            <span>{{ title }}</span>
        </div>
        <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center px-5">
            <div class="bg-white rounded-lg w-full max-w-sm mx-4">
                <div class="p-4 border-b flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-800">{{ title }}</h3>
                    <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-y-auto px-2">
                    <a
                            v-for="language in filteredLanguages"
                            :key="language.code"
                            :href="cleanUrl(language.url)"
                            class="flex items-center px-4 py-3 hover:bg-gray-100 border-b border-gray-100 rounded-md m-1"
                            :class="{'bg-blue-50': currentLocale === language.code}"
                    >
                        <div class="flex items-center">
                            <span class="w-10 font-semibold mr-2 text-gray-800">{{ language.code.toUpperCase() }}</span>
                            <span class="whitespace-nowrap text-gray-800">{{ language.native }}</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MobileLanguageSelector',
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
            showModal: false,
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
    watch: {
        showModal(val) {
            if (val) {
                document.body.classList.add('overflow-hidden');
            } else {
                document.body.classList.remove('overflow-hidden');
            }
        }
    },
    mounted() {
        document.addEventListener('keydown', this.handleEscKey);
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.handleEscKey);
    },
    methods: {
        handleEscKey(event) {
            if (event.key === 'Escape' && this.showModal) {
                this.showModal = false;
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
