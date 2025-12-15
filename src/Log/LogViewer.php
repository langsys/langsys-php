<?php

namespace Langsys\SDK\Log;

/**
 * Log viewer that renders logs in a Flowbite Tailwind UI.
 */
class LogViewer
{
    /**
     * @var string Path to log file
     */
    protected $logPath;

    /**
     * @var int Maximum number of entries to display
     */
    protected $maxEntries;

    /**
     * Create a new LogViewer instance.
     *
     * @param string $logPath Path to log file
     * @param int $maxEntries Maximum entries to display (0 = unlimited)
     */
    public function __construct($logPath, $maxEntries = 500)
    {
        $this->logPath = $logPath;
        $this->maxEntries = $maxEntries;
    }

    /**
     * Render the log viewer HTML page.
     *
     * @param string $minLevel Minimum level to display (debug, info, warning, error)
     * @return string HTML content
     */
    public function render($minLevel = 'debug')
    {
        $entries = $this->getEntries($minLevel);
        return $this->renderHtml($entries, $minLevel);
    }

    /**
     * Output the log viewer directly to the browser.
     * Supports query parameters:
     *   - ?level=debug|info|warning|error (filter level, default: debug)
     *   - ?format=json (return JSON instead of HTML)
     *   - ?action=clear (clear the log file)
     *
     * @return void
     */
    public function display()
    {
        // Get level from query string
        $validLevels = ['debug', 'info', 'warning', 'error'];
        $minLevel = isset($_GET['level']) && in_array($_GET['level'], $validLevels)
            ? $_GET['level']
            : 'debug';

        // Check for clear action
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        if ($action === 'clear') {
            $this->clear();
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true, 'message' => 'Log cleared']);
            return;
        }

        // Check if JSON format is requested (for AJAX polling)
        $format = isset($_GET['format']) ? $_GET['format'] : 'html';

        if ($format === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            echo $this->renderJson($minLevel);
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo $this->render($minLevel);
    }

    /**
     * Render log data as JSON for AJAX polling.
     *
     * @param string $minLevel Minimum level to include
     * @return string JSON content
     */
    public function renderJson($minLevel = 'debug')
    {
        $entries = $this->getEntries($minLevel);
        $stats = $this->getStats();

        return json_encode([
            'entries' => $entries,
            'stats' => $stats,
            'file_size' => $this->getFormattedFileSize(),
        ]);
    }

    /**
     * Get log entries from the file.
     *
     * @param string $minLevel Minimum level to include
     * @return array
     */
    public function getEntries($minLevel = 'debug')
    {
        if (!file_exists($this->logPath)) {
            return [];
        }

        $levelPriorities = [
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3,
        ];

        $minPriority = isset($levelPriorities[$minLevel]) ? $levelPriorities[$minLevel] : 0;

        $entries = [];
        $handle = fopen($this->logPath, 'r');

        if ($handle === false) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $entry = json_decode($line, true);
            if ($entry === null) {
                continue;
            }

            $entryPriority = isset($levelPriorities[$entry['level']])
                ? $levelPriorities[$entry['level']]
                : 0;

            if ($entryPriority >= $minPriority) {
                $entries[] = $entry;
            }
        }

        fclose($handle);

        // Return most recent entries first
        $entries = array_reverse($entries);

        // Limit entries
        if ($this->maxEntries > 0 && count($entries) > $this->maxEntries) {
            $entries = array_slice($entries, 0, $this->maxEntries);
        }

        return $entries;
    }

    /**
     * Get statistics about the log entries.
     *
     * @return array
     */
    public function getStats()
    {
        $entries = $this->getEntries('debug');

        $stats = [
            'total' => count($entries),
            'debug' => 0,
            'info' => 0,
            'warning' => 0,
            'error' => 0,
        ];

        foreach ($entries as $entry) {
            $level = isset($entry['level']) ? $entry['level'] : 'debug';
            if (isset($stats[$level])) {
                $stats[$level]++;
            }
        }

        return $stats;
    }

    /**
     * Render the HTML page.
     *
     * @param array $entries Log entries
     * @param string $currentLevel Current filter level
     * @return string
     */
    protected function renderHtml(array $entries, $currentLevel)
    {
        $stats = $this->getStats();
        $levelColors = [
            'debug' => 'gray',
            'info' => 'blue',
            'warning' => 'yellow',
            'error' => 'red',
        ];

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langsys SDK Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/json.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {}
            }
        }
    </script>
    <style>
        .log-entry { cursor: pointer; }
        .log-entry:hover { background-color: rgba(55, 65, 81, 0.5); }
        .context-content { display: none; }
        .context-content.show { display: table-row; }
        .log-entry.level-hidden, .log-entry.level-hidden + .context-content { display: none !important; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2">Langsys SDK Logs</h1>
                <p class="text-gray-400">Log file: <code class="bg-gray-800 px-2 py-1 rounded text-sm"><?php echo htmlspecialchars($this->logPath); ?></code></p>
            </div>
            <div class="flex items-center gap-3">
                <span id="realtime-status" class="text-gray-500 text-sm hidden">
                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse mr-1"></span>
                    Live
                </span>
                <button id="realtime-toggle"
                        onclick="toggleRealtime()"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-800 text-gray-300 hover:bg-gray-700 border border-gray-600">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span id="realtime-btn-text">Realtime</span>
                </button>
                <div class="relative">
                    <button id="hidden-toggle"
                            onclick="toggleHiddenPanel()"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-800 text-gray-300 hover:bg-gray-700 border border-gray-600">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                        </svg>
                        Hidden <span id="hidden-count" class="bg-gray-700 px-1.5 py-0.5 rounded text-xs ml-1">0</span>
                    </button>
                    <div id="hidden-panel" class="hidden absolute right-0 mt-2 w-80 bg-gray-800 border border-gray-700 rounded-lg shadow-xl z-50">
                        <button onclick="toggleHiddenPanel()" class="absolute -right-2 -top-2 w-6 h-6 bg-gray-700 hover:bg-gray-600 border border-gray-600 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        <div class="p-3 border-b border-gray-700 flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-300">Hidden Messages</span>
                            <button onclick="unhideAll()" class="text-xs text-blue-400 hover:text-blue-300">Unhide All</button>
                        </div>
                        <div id="hidden-list" class="max-h-64 overflow-y-auto">
                            <div class="p-3 text-gray-500 text-sm text-center">No hidden messages</div>
                        </div>
                    </div>
                </div>
                <button id="clear-logs"
                        onclick="clearLogs()"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-800 text-gray-300 hover:bg-red-700 hover:text-white border border-gray-600">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Clear
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div id="stat-total" class="text-2xl font-bold text-white"><?php echo $stats['total']; ?></div>
                <div class="text-gray-400 text-sm">Total Entries</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div id="stat-debug" class="text-2xl font-bold text-gray-400"><?php echo $stats['debug']; ?></div>
                <div class="text-gray-400 text-sm">Debug</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-blue-700">
                <div id="stat-info" class="text-2xl font-bold text-blue-400"><?php echo $stats['info']; ?></div>
                <div class="text-gray-400 text-sm">Info</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-yellow-700">
                <div id="stat-warning" class="text-2xl font-bold text-yellow-400"><?php echo $stats['warning']; ?></div>
                <div class="text-gray-400 text-sm">Warning</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-red-700">
                <div id="stat-error" class="text-2xl font-bold text-red-400"><?php echo $stats['error']; ?></div>
                <div class="text-gray-400 text-sm">Error</div>
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="mb-6 flex flex-wrap gap-2">
            <span class="text-gray-400 mr-2 self-center">Filter:</span>
            <?php foreach (['debug', 'info', 'warning', 'error'] as $level): ?>
            <button type="button"
                    id="filter-<?php echo $level; ?>"
                    onclick="setFilter('<?php echo $level; ?>')"
                    class="filter-btn px-4 py-2 rounded-lg text-sm font-medium transition-colors"
                    data-level="<?php echo $level; ?>"
                    data-color="<?php echo $levelColors[$level]; ?>">
                <?php echo ucfirst($level); ?>+
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Log Entries -->
        <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
            <!-- Empty state (shown via JS when no entries) -->
            <div id="empty-state" class="p-8 text-center text-gray-400 hidden">
                <svg class="mx-auto h-12 w-12 text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p>No log entries found</p>
                <p class="text-sm mt-1">Log entries will appear here once the SDK starts logging.</p>
            </div>
            <!-- Table (shown via JS when has entries) -->
            <div id="entries-table" class="overflow-x-auto hidden">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-400 uppercase bg-gray-900">
                        <tr>
                            <th scope="col" class="px-4 py-3 w-44">Timestamp</th>
                            <th scope="col" class="px-4 py-3 w-24">Level</th>
                            <th scope="col" class="px-4 py-3">Message</th>
                            <th scope="col" class="px-4 py-3 w-20">Context</th>
                        </tr>
                    </thead>
                    <tbody id="log-entries"></tbody>
                </table>
            </div>
        </div>

        <!-- Templates -->
        <template id="tpl-log-entry">
            <tr class="log-entry border-b border-gray-700 transition-colors group">
                <td class="px-4 py-3 font-mono text-xs text-gray-400">{{timestamp}}</td>
                <td class="px-4 py-3">
                    <span class="px-2.5 py-0.5 rounded text-xs font-medium bg-{{color}}-900 text-{{color}}-300">{{levelUpper}}</span>
                </td>
                <td class="px-4 py-3 text-gray-200">
                    <div class="flex items-center justify-between">
                        <span>{{message}}{{statusInfo}}</span>
                        <button class="hide-btn opacity-0 group-hover:opacity-100 ml-2 text-gray-500 hover:text-gray-300 transition-opacity" title="Hide this message type">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                        </button>
                    </div>
                </td>
                <td class="px-4 py-3">{{contextIndicator}}</td>
            </tr>
        </template>

        <template id="tpl-context-row">
            <tr class="context-content bg-gray-900 border-b border-gray-700">
                <td colspan="4" class="px-4 py-3">
                    <div class="relative">
                        <button class="close-btn absolute -right-1 -top-1 w-5 h-5 bg-gray-700 hover:bg-gray-600 border border-gray-600 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-colors z-10">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                        <div class="context-tabs">
                            <div class="tab-buttons flex gap-1 mb-2">
                                <button class="tab-btn active px-3 py-1 text-xs font-medium rounded bg-gray-700 text-white" data-tab="context">Context</button>
                                <button class="tab-btn hidden px-3 py-1 text-xs font-medium rounded bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-white" data-tab="payload">Payload</button>
                            </div>
                            <div class="tab-content" data-tab="context">
                                <pre class="bg-gray-950 rounded p-3 text-xs text-gray-300 overflow-x-auto max-h-96"><code class="context-code">{{contextJson}}</code></pre>
                            </div>
                            <div class="tab-content hidden" data-tab="payload">
                                <pre class="bg-gray-950 rounded p-3 text-xs text-gray-300 overflow-x-auto max-h-96"><code class="payload-code"></code></pre>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </template>

        <template id="tpl-hidden-item">
            <div class="flex items-center justify-between px-3 py-2 hover:bg-gray-700 border-b border-gray-700 last:border-0">
                <span class="text-sm text-gray-300 truncate mr-2" title="{{fullMessage}}">{{truncatedMessage}}</span>
                <button class="unhide-btn text-xs text-blue-400 hover:text-blue-300 whitespace-nowrap">Unhide</button>
            </div>
        </template>

        <template id="tpl-hidden-empty">
            <div class="p-3 text-gray-500 text-sm text-center">No hidden messages</div>
        </template>

        <template id="tpl-status-code">
            <span class="{{colorClass}} font-mono">{{code}}</span>
        </template>

        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>Showing <span id="showing-count"><?php echo count($entries); ?></span> of <span id="total-count"><?php echo $stats['total']; ?></span> entries<span id="hidden-notice" class="hidden"> (<span id="hidden-filtered">0</span> hidden)</span></p>
            <?php if ($this->maxEntries > 0): ?>
            <p id="limit-notice" class="mt-1 <?php echo $stats['total'] > $this->maxEntries ? '' : 'hidden'; ?>">Limited to most recent <?php echo $this->maxEntries; ?> entries</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
    <script>
        const STORAGE_KEY_FILTER = 'langsys_log_filter';
        const STORAGE_KEY_REALTIME = 'langsys_log_realtime';
        const STORAGE_KEY_HIDDEN = 'langsys_log_hidden';

        const levelColors = {
            debug: 'gray',
            info: 'blue',
            warning: 'yellow',
            error: 'red'
        };

        // Initial data from PHP (avoids extra fetch on page load)
        const initialData = <?php echo json_encode(['entries' => $entries, 'stats' => $stats], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

        let currentFilter = '<?php echo $currentLevel; ?>';
        let realtimeEnabled = false;
        let realtimeTimeout = null;
        let fetchInProgress = false;
        let hiddenMessages = [];
        let lastEntries = [];
        const POLL_INTERVAL = 2000; // 2 seconds
        const MAX_RENDER_ENTRIES = 500; // Client-side limit for DOM performance
        const maxEntries = <?php echo (int) $this->maxEntries; ?>; // Server-side limit

        function toggleHiddenPanel() {
            const panel = document.getElementById('hidden-panel');
            panel.classList.toggle('hidden');
        }

        function hideMessage(message) {
            if (!hiddenMessages.includes(message)) {
                hiddenMessages.push(message);
                localStorage.setItem(STORAGE_KEY_HIDDEN, JSON.stringify(hiddenMessages));
                updateHiddenUI();
                renderEntries(lastEntries);
            }
        }

        function unhideMessage(message) {
            hiddenMessages = hiddenMessages.filter(m => m !== message);
            localStorage.setItem(STORAGE_KEY_HIDDEN, JSON.stringify(hiddenMessages));
            updateHiddenUI();
            renderEntries(lastEntries);
        }

        function unhideAll() {
            hiddenMessages = [];
            localStorage.setItem(STORAGE_KEY_HIDDEN, JSON.stringify(hiddenMessages));
            updateHiddenUI();
            renderEntries(lastEntries);
        }

        function updateHiddenUI() {
            // Update count badge
            document.getElementById('hidden-count').textContent = hiddenMessages.length;

            // Update hidden list panel
            const list = document.getElementById('hidden-list');
            list.innerHTML = '';

            if (hiddenMessages.length === 0) {
                list.innerHTML = renderTemplate('tpl-hidden-empty', {});
            } else {
                hiddenMessages.forEach(msg => {
                    const truncated = msg.length > 40 ? msg.substring(0, 40) + '...' : msg;
                    const item = cloneTemplate('tpl-hidden-item', {
                        fullMessage: escapeHtml(msg),
                        truncatedMessage: escapeHtml(truncated)
                    });
                    item.querySelector('.unhide-btn').onclick = function() {
                        unhideMessage(msg);
                    };
                    list.appendChild(item);
                });
            }
        }

        const levelPriority = { debug: 0, info: 1, warning: 2, error: 3 };

        function setFilter(level) {
            currentFilter = level;
            localStorage.setItem(STORAGE_KEY_FILTER, level);
            updateFilterButtons();
            applyLevelFilter();
        }

        function applyLevelFilter() {
            const minPriority = levelPriority[currentFilter] || 0;
            let visibleCount = 0;
            let totalCount = 0;

            document.querySelectorAll('#log-entries .log-entry').forEach(row => {
                totalCount++;
                const rowLevel = row.dataset.level || 'debug';
                const rowPriority = levelPriority[rowLevel] || 0;

                if (rowPriority >= minPriority) {
                    row.classList.remove('level-hidden');
                    visibleCount++;
                } else {
                    row.classList.add('level-hidden');
                }
            });

            // Update footer and empty state
            document.getElementById('showing-count').textContent = visibleCount;
            updateEmptyState(visibleCount);
        }

        function updateEmptyState(visibleCount) {
            const emptyState = document.getElementById('empty-state');
            const entriesTable = document.getElementById('entries-table');
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
                entriesTable.classList.add('hidden');
            } else {
                emptyState.classList.add('hidden');
                entriesTable.classList.remove('hidden');
            }
        }

        function updateFilterButtons() {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                const level = btn.dataset.level;
                const color = btn.dataset.color;
                const isActive = level === currentFilter;

                // Remove all state classes
                btn.classList.remove(
                    'bg-gray-800', 'text-gray-300', 'hover:bg-gray-700',
                    'bg-gray-600', 'bg-blue-600', 'bg-yellow-600', 'bg-red-600',
                    'text-white', 'ring-2',
                    'ring-gray-500', 'ring-blue-500', 'ring-yellow-500', 'ring-red-500'
                );

                if (isActive) {
                    btn.classList.add('bg-' + color + '-600', 'text-white', 'ring-2', 'ring-' + color + '-500');
                } else {
                    btn.classList.add('bg-gray-800', 'text-gray-300', 'hover:bg-gray-700');
                }
            });
        }

        function toggleRealtime() {
            realtimeEnabled = !realtimeEnabled;
            localStorage.setItem(STORAGE_KEY_REALTIME, realtimeEnabled ? '1' : '0');
            updateRealtimeUI();

            if (realtimeEnabled) {
                startPolling();
            } else {
                stopPolling();
            }
        }

        function updateRealtimeUI() {
            const btn = document.getElementById('realtime-toggle');
            const btnText = document.getElementById('realtime-btn-text');
            const status = document.getElementById('realtime-status');

            if (realtimeEnabled) {
                btn.classList.remove('bg-gray-800', 'text-gray-300', 'hover:bg-gray-700');
                btn.classList.add('bg-green-600', 'text-white', 'hover:bg-green-700');
                btnText.textContent = 'Stop';
                status.classList.remove('hidden');
            } else {
                btn.classList.remove('bg-green-600', 'text-white', 'hover:bg-green-700');
                btn.classList.add('bg-gray-800', 'text-gray-300', 'hover:bg-gray-700');
                btnText.textContent = 'Realtime';
                status.classList.add('hidden');
            }
        }

        function startPolling() {
            fetchLogs(); // Fetch immediately (will schedule next poll on completion)
        }

        function stopPolling() {
            if (realtimeTimeout) {
                clearTimeout(realtimeTimeout);
                realtimeTimeout = null;
            }
        }

        function scheduleNextPoll() {
            if (realtimeEnabled) {
                realtimeTimeout = setTimeout(fetchLogs, POLL_INTERVAL);
            }
        }

        function clearLogs() {
            if (!confirm('Are you sure you want to clear all log entries? This cannot be undone.')) {
                return;
            }

            fetch('?action=clear')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fetchLogs(); // Refresh the display
                    }
                })
                .catch(err => console.error('Failed to clear logs:', err));
        }

        function fetchLogs() {
            if (fetchInProgress) return; // Prevent overlapping requests
            fetchInProgress = true;

            // Always fetch all entries (debug level), filter client-side
            fetch('?format=json&level=debug')
                .then(response => response.json())
                .then(data => {
                    updateStats(data.stats);
                    updateEntries(data.entries);
                    applyLevelFilter();
                })
                .catch(err => console.error('Failed to fetch logs:', err))
                .finally(() => {
                    fetchInProgress = false;
                    scheduleNextPoll(); // Schedule next poll after request completes
                });
        }

        function updateStats(stats) {
            document.getElementById('stat-total').textContent = stats.total;
            document.getElementById('stat-debug').textContent = stats.debug;
            document.getElementById('stat-info').textContent = stats.info;
            document.getElementById('stat-warning').textContent = stats.warning;
            document.getElementById('stat-error').textContent = stats.error;
        }

        function updateFooter(showing, total) {
            document.getElementById('showing-count').textContent = showing;
            document.getElementById('total-count').textContent = total;

            // Show hidden count if any messages are filtered
            const hiddenFiltered = total - showing;
            const hiddenNotice = document.getElementById('hidden-notice');
            const hiddenFilteredEl = document.getElementById('hidden-filtered');
            if (hiddenFiltered > 0) {
                hiddenFilteredEl.textContent = hiddenFiltered;
                hiddenNotice.classList.remove('hidden');
            } else {
                hiddenNotice.classList.add('hidden');
            }

            const limitNotice = document.getElementById('limit-notice');
            if (limitNotice) {
                if (maxEntries > 0 && total > maxEntries) {
                    limitNotice.classList.remove('hidden');
                } else {
                    limitNotice.classList.add('hidden');
                }
            }
        }

        function formatTimestamp(timestamp) {
            if (!timestamp) return '';
            const dt = new Date(timestamp);
            if (isNaN(dt.getTime())) return timestamp;
            return dt.toISOString().replace('T', ' ').substring(0, 19);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDuration(ms) {
            const val = parseFloat(ms);
            if (val >= 1000) {
                return Math.round(val / 1000) + 's';
            }
            return Math.round(val) + 'ms';
        }

        // Template rendering with handlebars-style {{variable}} placeholders
        function renderTemplate(templateId, data) {
            const template = document.getElementById(templateId);
            if (!template) return '';
            let html = template.innerHTML;
            for (const key in data) {
                html = html.replace(new RegExp('\\{\\{' + key + '\\}\\}', 'g'), data[key]);
            }
            return html;
        }

        function cloneTemplate(templateId, data) {
            const template = document.getElementById(templateId);
            if (!template) return null;
            const clone = template.content.cloneNode(true);

            // Determine wrapper type based on template content
            const firstEl = template.content.firstElementChild;
            const tagName = firstEl ? firstEl.tagName.toLowerCase() : '';
            let wrapper;

            // Use appropriate wrapper for table elements
            if (tagName === 'tr') {
                wrapper = document.createElement('tbody');
            } else if (tagName === 'td' || tagName === 'th') {
                wrapper = document.createElement('tr');
            } else {
                wrapper = document.createElement('div');
            }

            wrapper.appendChild(clone);
            let html = wrapper.innerHTML;
            for (const key in data) {
                html = html.replace(new RegExp('\\{\\{' + key + '\\}\\}', 'g'), data[key]);
            }
            wrapper.innerHTML = html;
            return wrapper.firstElementChild;
        }

        function getStatusCodeColor(code) {
            if (code >= 500) return 'red';
            if (code >= 400) return 'orange';
            if (code >= 300) return 'yellow';
            if (code >= 200) return 'green';
            return 'gray';
        }

        function formatStatusCode(code) {
            const color = getStatusCodeColor(code);
            const colorClasses = {
                green: 'text-green-400',
                yellow: 'text-yellow-400',
                orange: 'text-orange-400',
                red: 'text-red-400',
                gray: 'text-gray-400'
            };
            return renderTemplate('tpl-status-code', {
                colorClass: colorClasses[color],
                code: code
            });
        }

        function updateEntries(entries) {
            lastEntries = entries;
            const visibleEntries = entries.filter(entry => !hiddenMessages.includes(entry.message));

            // Find new entries by comparing timestamps
            const newEntries = findNewEntries(visibleEntries);

            if (newEntries.length > 0) {
                prependNewEntries(newEntries);
            }

            // Remove entries that should no longer be visible (due to maxEntries or hiding)
            trimExcessEntries(visibleEntries.length);

            updateFooter(visibleEntries.length, entries.length);
        }

        function getEntryId(entry) {
            // Create unique ID from timestamp + message
            return (entry.timestamp || '') + '|' + (entry.message || '');
        }

        function findNewEntries(visibleEntries) {
            const tbody = document.getElementById('log-entries');
            if (!tbody || tbody.children.length === 0) {
                return visibleEntries; // All entries are new (initial render)
            }

            // Get existing entry IDs from DOM
            const existingIds = new Set();
            tbody.querySelectorAll('tr[data-entry-id]').forEach(row => {
                existingIds.add(row.dataset.entryId);
            });

            // Find entries not in DOM
            return visibleEntries.filter(entry => !existingIds.has(getEntryId(entry)));
        }

        function prependNewEntries(newEntries) {
            const tbody = document.getElementById('log-entries');
            if (!tbody) return;

            // Prepend new entries (most recent first, so they go at top)
            newEntries.forEach((entry, idx) => {
                const rows = createEntryRows(entry, idx);
                rows.forEach(row => {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(-10px)';
                    tbody.insertBefore(row, tbody.firstChild);
                    // Trigger animation
                    requestAnimationFrame(() => {
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity = '1';
                        row.style.transform = 'translateY(0)';
                    });
                });
            });
        }

        function trimExcessEntries(targetCount) {
            const tbody = document.getElementById('log-entries');
            if (!tbody) return;

            // Count actual entry rows (not context rows)
            const entryRows = tbody.querySelectorAll('tr[data-entry-id]');
            const excess = entryRows.length - targetCount;

            if (excess > 0) {
                // Remove from bottom (oldest entries)
                for (let i = 0; i < excess; i++) {
                    const lastEntry = entryRows[entryRows.length - 1 - i];
                    if (lastEntry) {
                        // Also remove context row if exists
                        const contextRow = lastEntry.nextElementSibling;
                        if (contextRow && contextRow.classList.contains('context-content')) {
                            contextRow.remove();
                        }
                        lastEntry.remove();
                    }
                }
            }
        }

        function createEntryRows(entry, index) {
            const level = entry.level || 'debug';
            const color = levelColors[level] || 'gray';
            const timestamp = formatTimestamp(entry.timestamp);
            const message = entry.message || '';
            const context = entry.context;
            const entryId = getEntryId(entry);

            const rows = [];

            // Build status/duration info
            let statusInfo = '';
            if (context) {
                const parts = [];
                if (context.status_code !== undefined) {
                    parts.push(formatStatusCode(context.status_code));
                }
                if (context.http_code !== undefined && context.http_code !== 0) {
                    parts.push(formatStatusCode(context.http_code));
                }
                if (context.duration_ms !== undefined) {
                    parts.push('<span class="text-gray-500">' + formatDuration(context.duration_ms) + '</span>');
                }
                if (parts.length > 0) {
                    statusInfo = ' <span class="ml-2 text-xs">(' + parts.join(' · ') + ')</span>';
                }
            }

            // Build error details for error-level entries
            let errorDetails = '';
            if (level === 'error' && context) {
                const errorParts = [];

                // Show error message or response body
                let errorText = '';
                if (context.response_body) {
                    try {
                        const parsed = JSON.parse(context.response_body);
                        errorText = parsed.message || parsed.error || context.response_body;
                    } catch (e) {
                        errorText = context.response_body;
                    }
                } else if (context.error) {
                    errorText = context.error;
                }
                if (errorText) {
                    if (errorText.length > 150) {
                        errorText = errorText.substring(0, 150) + '...';
                    }
                    errorParts.push('<div class="text-red-400"><span class="text-red-600">Response:</span> ' + escapeHtml(errorText) + '</div>');
                }

                // Show payload hint if present (full payload in Payload tab)
                if (context.payload) {
                    errorParts.push('<div class="text-orange-400"><span class="text-orange-600">Payload:</span> <span class="text-gray-500">(click row to view full payload)</span></div>');
                }

                if (errorParts.length > 0) {
                    errorDetails = '<div class="mt-1 text-xs font-mono bg-gray-950 px-2 py-1 rounded space-y-1">' + errorParts.join('') + '</div>';
                }
            }

            // Main entry row using template
            const tr = cloneTemplate('tpl-log-entry', {
                timestamp: escapeHtml(timestamp),
                color: color,
                levelUpper: level.toUpperCase(),
                message: escapeHtml(message) + errorDetails,
                statusInfo: statusInfo,
                contextIndicator: context
                    ? '<span class="text-blue-400 text-xs font-medium">›</span>'
                    : '<span class="text-gray-600">-</span>'
            });

            tr.dataset.entryId = entryId;
            tr.dataset.level = level;
            tr.onclick = function(e) {
                // Don't toggle if clicking on a button
                if (e.target.closest('button')) return;
                toggleContextByEntry(this);
            };

            // Attach hide button handler
            const hideBtn = tr.querySelector('.hide-btn');
            if (hideBtn) {
                hideBtn.onclick = function(e) {
                    e.stopPropagation();
                    hideMessage(message);
                };
            }

            rows.push(tr);

            // Context row (if has context)
            if (context) {
                // Separate payload from context for display
                const payload = context.payload;
                const displayContext = Object.assign({}, context);
                delete displayContext.payload;

                // Try to parse response_body as JSON for pretty printing
                if (displayContext.response_body && typeof displayContext.response_body === 'string') {
                    try {
                        displayContext.response_body = JSON.parse(displayContext.response_body);
                    } catch (e) {
                        // Not JSON, leave as string
                    }
                }

                const contextTr = cloneTemplate('tpl-context-row', {
                    contextJson: '' // We'll set this after for highlighting
                });

                // Set context and apply highlighting
                const contextCode = contextTr.querySelector('.context-code');
                if (contextCode) {
                    const contextJson = JSON.stringify(displayContext, null, 2);
                    const highlighted = hljs.highlight(contextJson, {language: 'json'});
                    contextCode.innerHTML = highlighted.value;
                    contextCode.classList.add('hljs', 'language-json');
                }

                // Show payload tab if we have payload
                if (payload) {
                    const payloadBtn = contextTr.querySelector('.tab-btn[data-tab="payload"]');
                    const payloadCode = contextTr.querySelector('.payload-code');
                    if (payloadBtn) {
                        payloadBtn.classList.remove('hidden');
                    }
                    if (payloadCode) {
                        // Try to format as JSON
                        let formatted = payload;
                        try {
                            const parsed = JSON.parse(payload);
                            formatted = JSON.stringify(parsed, null, 2);
                        } catch (e) {
                            // Not JSON, use as-is
                        }
                        const highlighted = hljs.highlight(formatted, {language: 'json'});
                        payloadCode.innerHTML = highlighted.value;
                        payloadCode.classList.add('hljs', 'language-json');
                    }
                }

                // Attach tab switching handlers
                const tabBtns = contextTr.querySelectorAll('.tab-btn');
                const tabContents = contextTr.querySelectorAll('.tab-content');
                tabBtns.forEach(btn => {
                    btn.onclick = function(e) {
                        e.stopPropagation();
                        const tab = btn.dataset.tab;
                        tabBtns.forEach(b => {
                            b.classList.remove('active', 'bg-gray-700', 'text-white');
                            b.classList.add('bg-gray-800', 'text-gray-400');
                        });
                        btn.classList.add('active', 'bg-gray-700', 'text-white');
                        btn.classList.remove('bg-gray-800', 'text-gray-400');
                        tabContents.forEach(c => {
                            c.classList.toggle('hidden', c.dataset.tab !== tab);
                        });
                    };
                });

                // Attach close button handler
                const closeBtn = contextTr.querySelector('.close-btn');
                if (closeBtn) {
                    closeBtn.onclick = function() {
                        contextTr.classList.remove('show');
                    };
                }

                rows.push(contextTr);
            }

            return rows;
        }

        function toggleContextByEntry(el) {
            // el can be a button or the row itself
            const row = el.tagName === 'TR' ? el : el.closest('tr');
            const contextRow = row.nextElementSibling;
            if (contextRow && contextRow.classList.contains('context-content')) {
                contextRow.classList.toggle('show');
            }
        }

        function renderEntries(entries) {
            // Full re-render (used for initial load and when hiding/unhiding)
            const tbody = document.getElementById('log-entries');
            if (!tbody) return;

            // Filter hidden messages and limit to max entries
            const visibleEntries = entries
                .filter(entry => !hiddenMessages.includes(entry.message))
                .slice(0, MAX_RENDER_ENTRIES);

            // Clear and rebuild
            tbody.innerHTML = '';

            visibleEntries.forEach((entry, index) => {
                const rows = createEntryRows(entry, index);
                rows.forEach(row => tbody.appendChild(row));
            });

            // Apply level filter and update UI
            applyLevelFilter();
            updateFooter(visibleEntries.length, entries.length);
        }

        // Initialize on page load
        (function init() {
            // Restore filter from localStorage (if available)
            const savedFilter = localStorage.getItem(STORAGE_KEY_FILTER);
            if (savedFilter && ['debug', 'info', 'warning', 'error'].includes(savedFilter)) {
                currentFilter = savedFilter;
            }

            // Restore realtime mode from localStorage
            const savedRealtime = localStorage.getItem(STORAGE_KEY_REALTIME);
            if (savedRealtime === '1') {
                realtimeEnabled = true;
            }

            // Restore hidden messages from localStorage
            const savedHidden = localStorage.getItem(STORAGE_KEY_HIDDEN);
            if (savedHidden) {
                try {
                    hiddenMessages = JSON.parse(savedHidden);
                    if (!Array.isArray(hiddenMessages)) hiddenMessages = [];
                } catch (e) {
                    hiddenMessages = [];
                }
            }

            // Apply initial UI state
            updateFilterButtons();
            updateRealtimeUI();
            updateHiddenUI();

            // Render initial data (always debug level, filtered client-side)
            lastEntries = initialData.entries;
            updateStats(initialData.stats);
            renderEntries(initialData.entries);

            // Start polling if realtime was enabled
            if (realtimeEnabled) {
                startPolling();
            }

            // Close hidden panel when clicking outside
            document.addEventListener('click', function(e) {
                const panel = document.getElementById('hidden-panel');
                const toggle = document.getElementById('hidden-toggle');
                if (!panel.contains(e.target) && !toggle.contains(e.target)) {
                    panel.classList.add('hidden');
                }
            });

            // Legacy auto-refresh option (page reload)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('refresh')) {
                const interval = parseInt(urlParams.get('refresh')) * 1000;
                if (interval >= 1000) {
                    setTimeout(() => location.reload(), interval);
                }
            }
        })();
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Clear the log file.
     *
     * @return bool
     */
    public function clear()
    {
        if (!file_exists($this->logPath)) {
            return true;
        }

        return file_put_contents($this->logPath, '') !== false;
    }

    /**
     * Get the log file size in bytes.
     *
     * @return int
     */
    public function getFileSize()
    {
        if (!file_exists($this->logPath)) {
            return 0;
        }

        return filesize($this->logPath);
    }

    /**
     * Get formatted file size.
     *
     * @return string
     */
    public function getFormattedFileSize()
    {
        $bytes = $this->getFileSize();

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
