<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Knowledge Graph') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-zinc-900 dark:to-zinc-800">
        {{-- Navigation --}}
        <nav class="border-b border-zinc-200 dark:border-zinc-700 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-600 rounded-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <span class="text-xl font-semibold text-zinc-900 dark:text-white">
                            {{ config('app.name', 'Knowledge Graph') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-4">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="text-sm font-medium text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                                    Log in
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                                        Get Started
                                    </a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>
            </div>
        </nav>

        {{-- Hero Section --}}
        <div class="relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-16">
                <div class="text-center">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 mb-8">
                        <span class="flex h-2 w-2 rounded-full bg-green-500"></span>
                        <span class="text-sm font-medium text-blue-700 dark:text-blue-400">Now with Production RAG</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-zinc-900 dark:text-white tracking-tight">
                        Get Accurate Answers
                        <span class="text-blue-600 dark:text-blue-400">From Your Documents</span>
                    </h1>
                    <p class="mt-6 text-lg sm:text-xl text-zinc-600 dark:text-zinc-400 max-w-3xl mx-auto">
                        Stop searching through folders. Ask questions in plain English and get verified answers 
                        with sources — no hallucinations, no guesswork.
                    </p>
                    <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white text-base font-medium rounded-lg transition-colors">
                                Upload Your First Document
                                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                        @endif
                        <a href="#how-it-works" class="inline-flex items-center justify-center px-6 py-3 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-300 dark:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-base font-medium rounded-lg transition-colors">
                            See How It Works
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Trust Indicators --}}
        <div class="border-y border-zinc-200 dark:border-zinc-700 bg-white/50 dark:bg-zinc-900/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-wrap justify-center gap-8 md:gap-12">
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Anti-hallucination validation
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Your data stays private
                    </div>
                    <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                        Open source — you own everything
                    </div>
                </div>
            </div>
        </div>

        {{-- Key Features Section --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-bold text-zinc-900 dark:text-white mb-4">
                    Built for Real Work
                </h2>
                <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                    Not just another search tool. Get answers you can trust, backed by your actual documents.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Feature 1: Intelligent Search --}}
                <div class="bg-white dark:bg-zinc-800 rounded-2xl p-8 shadow-sm border border-zinc-200 dark:border-zinc-700">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-3">Intelligent Search</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                        Find answers even when you don't know the exact keywords. Our system understands what you mean, not just what you type.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Natural language queries
                        </li>
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Semantic understanding
                        </li>
                    </ul>
                </div>

                {{-- Feature 2: Verified Answers --}}
                <div class="bg-white dark:bg-zinc-800 rounded-2xl p-8 shadow-sm border border-zinc-200 dark:border-zinc-700">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-3">Verified Answers</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                        Every answer is verified against your documents. No made-up information — see exactly where the answer came from.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Source citations included
                        </li>
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Confidence scoring
                        </li>
                    </ul>
                </div>

                {{-- Feature 3: Document Intelligence --}}
                <div class="bg-white dark:bg-zinc-800 rounded-2xl p-8 shadow-sm border border-zinc-200 dark:border-zinc-700">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-3">Document Intelligence</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                        Automatically extracts key questions from your content. The system improves over time as you add more documents.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Auto-generated Q&A
                        </li>
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Continuous learning
                        </li>
                    </ul>
                </div>

                {{-- Feature 4: Enterprise Ready --}}
                <div class="bg-white dark:bg-zinc-800 rounded-2xl p-8 shadow-sm border border-zinc-200 dark:border-zinc-700">
                    <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-3">Enterprise Ready</h3>
                    <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                        Full audit trail and compliance built in. Security tested and monitored for peace of mind.
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Complete audit logging
                        </li>
                        <li class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Security monitoring
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- How It Works Section --}}
        <div id="how-it-works" class="bg-white dark:bg-zinc-800 border-y border-zinc-200 dark:border-zinc-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="text-center mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold text-zinc-900 dark:text-white mb-4">
                        How It Works
                    </h2>
                    <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                        From documents to answers in three simple steps.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {{-- Step 1 --}}
                    <div class="relative text-center">
                        <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">
                            1
                        </div>
                        <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-3">Upload Documents</h3>
                        <p class="text-zinc-600 dark:text-zinc-400">
                            Drag and drop your PDFs, Word docs, or text files. We handle the processing automatically.
                        </p>
                        <div class="hidden md:block absolute top-8 left-[60%] w-[80%] h-0.5 bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>

                    {{-- Step 2 --}}
                    <div class="relative text-center">
                        <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">
                            2
                        </div>
                        <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-3">Ask Questions</h3>
                        <p class="text-zinc-600 dark:text-zinc-400">
                            Type your questions in plain English. No need to remember exact keywords or file names.
                        </p>
                        <div class="hidden md:block absolute top-8 left-[60%] w-[80%] h-0.5 bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>

                    {{-- Step 3 --}}
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 text-white text-2xl font-bold">
                            3
                        </div>
                        <h3 class="text-xl font-semibold text-zinc-900 dark:text-white mb-3">Get Verified Answers</h3>
                        <p class="text-zinc-600 dark:text-zinc-400">
                            Receive accurate answers with direct links to the source documents for verification.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Call to Action --}}
        <div class="relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-3xl p-8 sm:p-12 lg:p-16 text-center">
                    <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">
                        Start Organizing Your Knowledge
                    </h2>
                    <p class="text-lg text-blue-100 max-w-2xl mx-auto mb-8">
                        Join teams who've stopped searching and started finding. Get set up in minutes, not days.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-4 bg-white text-blue-600 hover:bg-blue-50 text-base font-semibold rounded-lg transition-colors">
                                Try It Now — Free
                                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                        @endif
                        <a href="https://github.com/laravel/livewire-starter-kit" target="_blank" class="inline-flex items-center justify-center px-8 py-4 bg-blue-700 hover:bg-blue-800 text-white text-base font-semibold rounded-lg border border-blue-500 transition-colors">
                            <svg class="mr-2 w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                            </svg>
                            View on GitHub
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <footer class="border-t border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Built with Laravel {{ Illuminate\Foundation\Application::VERSION }} and Livewire
                    </p>
                    <div class="flex items-center gap-6">
                        <a href="https://laravel.com/docs" target="_blank" class="text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                            Documentation
                        </a>
                        <a href="https://github.com/laravel/livewire-starter-kit" target="_blank" class="text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white">
                            GitHub
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </body>
</html>
