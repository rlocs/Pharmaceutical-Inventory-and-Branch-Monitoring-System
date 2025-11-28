<?php
// includes/sent_emails.php
// Purpose: View receipts generated in "Demo Mode"

// 1. Define path to the 'handlers/sent_emails' folder
// Assuming structure: root/includes/sent_emails.php AND root/handlers/sent_emails/
$folderPath = __DIR__ . '/../handlers/sent_emails';

// 2. CSS for the viewer
?>
<div class="bg-white p-6 rounded-lg shadow-md h-full flex flex-col">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">Sent Receipts (Demo Mode)</h2>
        <button onclick="location.reload()" class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded text-gray-600">
            Refresh List
        </button>
    </div>

    <div class="flex-1 overflow-y-auto border border-gray-200 rounded-lg">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 sticky top-0">
                <tr>
                    <th class="p-4 border-b text-xs font-semibold text-gray-500 uppercase">Date Sent</th>
                    <th class="p-4 border-b text-xs font-semibold text-gray-500 uppercase">File Name</th>
                    <th class="p-4 border-b text-xs font-semibold text-gray-500 uppercase text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php
                if (is_dir($folderPath)) {
                    // Get all .html files, order by newest first
                    $files = glob($folderPath . '/*.html');
                    usort($files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });

                    if (count($files) > 0) {
                        foreach ($files as $file) {
                            $filename = basename($file);
                            $date = date("F j, Y, g:i a", filemtime($file));
                            
                            // Create a link to view the raw HTML file
                            // NOTE: Adjust the href path '../handlers/sent_emails/' if your URL structure is different
                            $viewLink = "handlers/sent_emails/" . $filename;
                            
                            echo "
                            <tr class='hover:bg-gray-50 transition'>
                                <td class='p-4 text-sm text-gray-600'>{$date}</td>
                                <td class='p-4 text-sm font-medium text-gray-800'>{$filename}</td>
                                <td class='p-4 text-right'>
                                    <a href='{$viewLink}' target='_blank' class='text-blue-600 hover:text-blue-800 text-sm font-bold underline'>
                                        View Receipt
                                    </a>
                                </td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3' class='p-8 text-center text-gray-500'>No emails sent yet. Try making a transaction!</td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' class='p-8 text-center text-red-500'>
                        Folder not found: <code>handlers/sent_emails</code><br>
                        <span class='text-xs text-gray-400'>Make a transaction first to generate this folder.</span>
                    </td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>