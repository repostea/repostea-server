@extends('layouts.app')

@section('title', __('manifesto.title'))

@section('page', 'manifesto')

@section('content')
    <div class="container mx-auto px-6 py-16">
        <div class="text-center mb-16">
            <h1 class="manifesto-title text-4xl md:text-5xl font-bold mb-4">{{ __('manifesto.title') }}</h1>
            <p class="manifesto-subtitle text-xl">{{ __('manifesto.subtitle') }}</p>
        </div>

        <div class="manifesto-section mb-16">
            @foreach([
                'intro', 'plural', 'content', 'moderation', 'advertising', 'information',
                'sustainability', 'transparency', 'development', 'education', 'privacy', 'governance', 'invitation'
            ] as $section)
                <div class="mb-10">
                    <h3 class="text-2xl font-bold mb-4">{{ __("manifesto.main_manifesto.{$section}_title") }}</h3>
                    <p class="text-gray-700">
                        {!! nl2br(e(__("manifesto.main_manifesto.$section"))) !!}
                    </p>
                </div>
            @endforeach
            <p class="closing text-xl italic text-center mt-12">{{ __('manifesto.main_manifesto.closing') }}</p>
        </div>

        <div class="mb-12 text-center">
            <p class="text-lg mb-4">{{ __('manifesto.more_about_vision') }}</p>
            <a href="{{ localized_route('about') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-md inline-block transition">
                {{ __('manifesto.learn_more_about_us') }}
            </a>
        </div>

        <div class="bg-gray-50 p-8 rounded-xl text-center mb-8 border border-gray-200">
            <p class="text-gray-700 mb-4">{{ __('manifesto.development.text1') }}</p>
            <a href="{{ config('site.github_url', 'https://github.com/repostea') }}"
               class="inline-flex items-center text-blue-600 font-semibold hover:text-blue-700 transition">
                <i class="fab fa-github mr-2"></i> {{ __('manifesto.development.link') }}
            </a>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const signForm = document.getElementById('signForm');

            if (signForm) {
                setupAjaxForm(signForm, 'sign');
            }

            function setupAjaxForm(form, formType) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const submitButton = form.querySelector('button[type="submit"]');
                    const originalText = submitButton.textContent;
                    submitButton.disabled = true;

                    submitButton.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white rounded-full border-t-transparent animate-spin mr-2"></span> ' + originalText;

                    const formData = new FormData(form);

                    if (!formData.has('_token')) {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                        formData.append('_token', csrfToken);
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                        .then(response => response.json())
                        .then(data => {
                            submitButton.disabled = false;
                            submitButton.textContent = originalText;

                            const formSection = form.closest('.form-section');
                            let alertDiv = document.createElement('div');

                            if (data.success) {
                                form.reset();

                                if (data.is_duplicate) {
                                    alertDiv.className = 'bg-yellow-50 text-yellow-800 p-4 mb-4 rounded-md flex items-center';
                                    alertDiv.setAttribute('role', 'alert');
                                    alertDiv.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-yellow-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <p class="font-medium">${data.message}</p>`;
                                } else {
                                    alertDiv.className = 'bg-green-50 text-green-800 p-4 mb-4 rounded-md flex items-center';
                                    alertDiv.setAttribute('role', 'alert');
                                    alertDiv.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <p class="font-medium">${data.message}</p>`;
                                }

                                if (data.count) {
                                    const counterElement = form.nextElementSibling;
                                    if (counterElement) {
                                        const oldCount = parseInt(counterElement.textContent.match(/\d+/)[0]);
                                        const newCount = data.count;

                                        let currentCount = oldCount;
                                        const countInterval = setInterval(() => {

                                            currentCount++;
                                            counterElement.textContent = counterElement.textContent.replace(/\d+/, currentCount);

                                            if (currentCount >= newCount) {

                                                clearInterval(countInterval);
                                                counterElement.classList.add('text-green-600', 'font-medium');
                                                setTimeout(() => {
                                                    counterElement.classList.remove('text-green-600', 'font-medium');
                                                }, 2000);
                                            }
                                        }, 50);
                                    }
                                }
                            } else {
                                alertDiv.className = 'bg-red-50 text-red-800 p-4 mb-4 rounded-md';
                                alertDiv.setAttribute('role', 'alert');
                                alertDiv.innerHTML = `<p>${data.message}</p>`;
                            }

                            const existingAlert = formSection.querySelector('[role="alert"]');
                            if (existingAlert) {
                                existingAlert.remove();
                            }

                            alertDiv.style.marginTop = '1.5rem';
                            alertDiv.style.marginBottom = '1.5rem';
                            formSection.appendChild(alertDiv);

                            setTimeout(() => {
                                alertDiv.style.transition = 'opacity 0.5s';
                                alertDiv.style.opacity = '0';
                                setTimeout(() => alertDiv.remove(), 500);
                            }, 20000);
                        })
                        .catch(error => {
                            submitButton.disabled = false;
                            submitButton.textContent = originalText;
                            console.error('Error:', error);
                        });
                });
            }
        });
    </script>
@endpush

@push('styles')
    <style>
        .manifesto-title {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #1F2937;
        }

        .manifesto-subtitle {
            color: #4B5563;
        }

        .manifesto-section h3 {
            color: #1F2937;
        }

        .principles-list li strong {
            color: #1F2937;
        }

        .btn-primary {
            background-color: #2563EB;
            color: white;
        }

        .btn-primary:hover {
            background-color: #1D4ED8;
        }
    </style>
@endpush
