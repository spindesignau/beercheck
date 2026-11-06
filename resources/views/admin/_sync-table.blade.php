<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
<table class="w-full text-left text-sm">
    <thead class="bg-stone-100"><tr>
        <th class="p-2">When</th><th>Source</th><th>Status</th><th>Fetched</th><th>Filtered</th><th>New</th><th>Updated</th><th>Deactivated</th><th>Geocoded</th><th>Skipped</th>
    </tr></thead>
    <tbody>
    @forelse ($runs as $run)
        <tr class="border-t border-stone-100 align-top">
            <td class="p-2 whitespace-nowrap">{{ $run->created_at->timezone('Australia/Sydney')->format('d M Y H:i') }}@if($run->dry_run) <span class="text-xs">(dry)</span>@endif</td>
            <td>{{ $run->source }}</td>
            <td><span @class(['font-bold', 'text-emerald-700' => $run->status === 'completed', 'text-red-600' => $run->status === 'failed', 'text-amber-600' => $run->status === 'running'])>{{ $run->status }}</span></td>
            <td>{{ $run->fetched_count }}</td><td>{{ $run->filtered_count }}</td><td>{{ $run->inserted_count }}</td>
            <td>{{ $run->updated_count }}</td><td>{{ $run->deactivated_count }}</td><td>{{ $run->geocoded_count }}</td><td>{{ $run->skipped_count }}</td>
        </tr>
        @if ($run->error_log)
            <tr><td colspan="10" class="px-2 pb-2"><details><summary class="cursor-pointer text-xs text-red-700">Log</summary><pre class="max-h-48 overflow-auto whitespace-pre-wrap text-xs">{{ $run->error_log }}</pre></details></td></tr>
        @endif
    @empty
        <tr><td colspan="10" class="p-3 text-stone-500">No runs yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
