<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">
                    RAG Quality Dashboard
                </h2>
                <p class="mt-1 text-gray-600 dark:text-gray-400">
                    Monitor RAG system performance, user satisfaction, and quality metrics
                </p>
            </div>
            <div class="flex gap-2">
                <button wire:click="setTimePeriod(7)" 
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                    {{ $timePeriod === 7 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    7 Days
                </button>
                <button wire:click="setTimePeriod(30)" 
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                    {{ $timePeriod === 30 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    30 Days
                </button>
                <button wire:click="setTimePeriod(90)" 
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                    {{ $timePeriod === 90 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                    90 Days
                </button>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
            {{-- Total Queries --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Queries</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['total_queries']) }}</p>
                    </div>
                </div>
            </div>

            {{-- Avg Confidence --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Confidence</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ round($summary['avg_confidence'] * 100, 1) }}%</p>
                    </div>
                </div>
            </div>

            {{-- Validation Pass Rate --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Validation Pass</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ round($summary['validation_pass_rate'] * 100, 1) }}%</p>
                    </div>
                </div>
            </div>

            {{-- Satisfaction Rate --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-yellow-100 dark:bg-yellow-900">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Satisfaction</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ round($summary['satisfaction_rate'] * 100, 1) }}%</p>
                    </div>
                </div>
            </div>

            {{-- Avg Latency --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-orange-100 dark:bg-orange-900">
                        <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Latency</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ round($summary['avg_latency_ms'] / 1000, 2) }}s</p>
                    </div>
                </div>
            </div>

            {{-- Estimated Cost --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-pink-100 dark:bg-pink-900">
                        <svg class="w-6 h-6 text-pink-600 dark:text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Est. Cost</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($summary['estimated_cost'], 4) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Query Volume Chart --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Daily Query Volume</h3>
                <div class="h-64 flex items-end justify-between gap-1">
                    @forelse($dailyVolume as $day)
                        <div class="flex-1 flex flex-col items-center">
                            @php
                                $maxCount = max(array_column($dailyVolume, 'count')) ?: 1;
                                $height = ($day['count'] / $maxCount) * 100;
                            @endphp
                            <div class="w-full bg-blue-500 rounded-t transition-all duration-300 hover:bg-blue-600 relative group"
                                 style="height: {{ $height }}%">
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 whitespace-nowrap z-10">
                                    {{ $day['count'] }} queries
                                </div>
                            </div>
                            <span class="text-xs text-gray-500 mt-2 transform -rotate-45 origin-top-left translate-y-4">
                                {{ \Carbon\Carbon::parse($day['date'])->format('M d') }}
                            </span>
                        </div>
                    @empty
                        <div class="w-full h-full flex items-center justify-center text-gray-500">
                            No data available
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Confidence Trend --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Confidence Score Trend</h3>
                <div class="h-64 relative">
                    @if(count($confidenceTrend) > 0)
                        <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            @php
                                $points = [];
                                $maxIndex = count($confidenceTrend) - 1;
                                foreach($confidenceTrend as $i => $day) {
                                    $x = $maxIndex > 0 ? ($i / $maxIndex) * 100 : 50;
                                    $y = 100 - ($day['confidence'] * 100);
                                    $points[] = "$x,$y";
                                }
                                $pointsStr = implode(' ', $points);
                            @endphp
                            <polyline
                                fill="none"
                                stroke="#10B981"
                                stroke-width="2"
                                points="{{ $pointsStr }}"
                            />
                            @foreach($confidenceTrend as $i => $day)
                                @php
                                    $x = $maxIndex > 0 ? ($i / $maxIndex) * 100 : 50;
                                    $y = 100 - ($day['confidence'] * 100);
                                @endphp
                                <circle cx="{{ $x }}" cy="{{ $y }}" r="1.5" fill="#10B981" />
                            @endforeach
                        </svg>
                        <div class="absolute left-0 top-0 text-xs text-gray-500">100%</div>
                        <div class="absolute left-0 bottom-0 text-xs text-gray-500">0%</div>
                    @else
                        <div class="w-full h-full flex items-center justify-center text-gray-500">
                            No data available
                        </div>
                    @endif
                </div>
            </div>

            {{-- User Satisfaction --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User Satisfaction</h3>
                <div class="flex items-center justify-center">
                    <div class="relative w-48 h-48">
                        @if($satisfactionData['total'] > 0)
                            @php
                                $positiveAngle = ($satisfactionData['positive'] / $satisfactionData['total']) * 360;
                            @endphp
                            <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90">
                                {{-- Background circle --}}
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#E5E7EB" stroke-width="20" />
                                {{-- Positive segment --}}
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#10B981" stroke-width="20"
                                    stroke-dasharray="{{ ($positiveAngle / 360) * 251.2 }} 251.2"
                                    stroke-linecap="round" />
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">
                                    {{ round($satisfactionData['rate'] * 100, 0) }}%
                                </span>
                                <span class="text-sm text-gray-500">Positive</span>
                            </div>
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-500">
                                No feedback yet
                            </div>
                        @endif
                    </div>
                </div>
                <div class="mt-4 flex justify-center gap-6">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            👍 {{ $satisfactionData['positive'] }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-gray-300"></div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            👎 {{ $satisfactionData['negative'] }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Token Usage --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Token Usage</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">Input Tokens</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($tokenUsage['total_input']) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">Output Tokens</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($tokenUsage['total_output']) }}</span>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Total Tokens</span>
                            <span class="font-semibold text-lg text-gray-900 dark:text-white">{{ number_format($tokenUsage['total']) }}</span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">Avg per Query</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($tokenUsage['avg_per_query']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Failing Queries Section --}}
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top Failing Queries</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-3 text-sm font-medium text-gray-600 dark:text-gray-400">Query</th>
                            <th class="pb-3 text-sm font-medium text-gray-600 dark:text-gray-400">Confidence</th>
                            <th class="pb-3 text-sm font-medium text-gray-600 dark:text-gray-400">Status</th>
                            <th class="pb-3 text-sm font-medium text-gray-600 dark:text-gray-400">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($failingQueries as $query)
                            <tr>
                                <td class="py-3 text-sm text-gray-900 dark:text-white max-w-md truncate">
                                    {{ $query['query'] }}
                                </td>
                                <td class="py-3 text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                        {{ $query['confidence_score'] >= 0.7 ? 'bg-green-100 text-green-800' : ($query['confidence_score'] >= 0.5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ round($query['confidence_score'] * 100, 0) }}%
                                    </span>
                                </td>
                                <td class="py-3 text-sm">
                                    @if($query['validation_passed'])
                                        <span class="text-green-600 dark:text-green-400">✓ Passed</span>
                                    @else
                                        <span class="text-red-600 dark:text-red-400">✗ Failed</span>
                                    @endif
                                </td>
                                <td class="py-3 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $query['created_at'] }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                    No failing queries found. Great job!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Recent Feedback Section --}}
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent User Feedback</h3>
            <div class="space-y-4">
                @forelse($recentFeedback as $feedback)
                    <div class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="text-2xl">
                            {{ $feedback['rating'] === 'thumbs_up' ? '👍' : '👎' }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $feedback['user_name'] }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">• {{ $feedback['created_at'] }}</span>
                            </div>
                            @if($feedback['comment'])
                                <p class="text-gray-600 dark:text-gray-300 text-sm">"{{ $feedback['comment'] }}"</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-1">Query: {{ Str($feedback['query_id'])->limit(20) }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No feedback received yet.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Validation Stats --}}
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Validation Node Performance</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($validationStats as $node => $stats)
                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium text-gray-900 dark:text-white capitalize">{{ $node }}</span>
                            <span class="text-sm font-semibold {{ $stats['pass_rate'] >= 0.9 ? 'text-green-600' : ($stats['pass_rate'] >= 0.7 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ round($stats['pass_rate'] * 100, 1) }}%
                            </span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $stats['passed'] }} passed / {{ $stats['failed'] }} failed
                        </div>
                        <div class="mt-2 h-2 bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: {{ $stats['pass_rate'] * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Actions Footer --}}
        <div class="mt-8 flex justify-between items-center">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Last updated: {{ now()->format('Y-m-d H:i:s') }}
            </div>
            <div class="flex gap-4">
                <a href="{{ route('api.rag.query') }}" target="_blank" 
                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Test RAG Query
                </a>
                <button onclick="window.print()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Print Report
                </button>
            </div>
        </div>
    </div>
</div>
