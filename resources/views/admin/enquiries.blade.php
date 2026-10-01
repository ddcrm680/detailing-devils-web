<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enquiries · {{ config('site.brand') }}</title>
<style>
  body{margin:0;background:#070707;color:#EDEAE4;font:15px/1.5 "Helvetica Neue",Arial,sans-serif;padding:32px 16px}
  h1{font-size:22px;letter-spacing:.06em;text-transform:uppercase;margin:0 0 6px}
  p{color:#8E8B86;margin:0 0 24px}
  .wrap{max-width:1100px;margin:0 auto}
  .scroll{overflow-x:auto;border:1px solid #212120}
  table{width:100%;border-collapse:collapse;min-width:720px}
  th,td{text-align:left;padding:12px 14px;border-bottom:1px solid #212120;vertical-align:top}
  th{font:11px/1 ui-monospace,Menlo,monospace;letter-spacing:.14em;text-transform:uppercase;color:#8E8B86;background:#0E0E0E}
  td a{color:#EDEAE4}
  .tag{display:inline-block;padding:2px 8px;border:1px solid #D7232C;color:#ff6b72;font-size:12px}
  .empty{padding:40px;text-align:center;color:#8E8B86}
  nav{margin-top:20px}
  nav a,nav span{color:#EDEAE4;margin-right:12px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Website enquiries</h1>
  <p>{{ $enquiries->total() }} total · newest first</p>
  <div class="scroll">
    <table>
      <thead><tr><th>Received</th><th>Name</th><th>Phone</th><th>City</th><th>Car</th><th>Interested in</th></tr></thead>
      <tbody>
      @forelse($enquiries as $e)
        <tr>
          <td>{{ $e->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
          <td>{{ $e->name }}</td>
          <td><a href="tel:{{ preg_replace('/[^0-9+]/', '', $e->phone) }}">{{ $e->phone }}</a></td>
          <td>{{ $e->city }}</td>
          <td>{{ $e->car }}</td>
          <td><span class="tag">{{ $e->interest }}</span></td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty">No enquiries yet.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <nav>
    @if($enquiries->previousPageUrl())<a href="{{ $enquiries->previousPageUrl() }}">← Newer</a>@endif
    @if($enquiries->nextPageUrl())<a href="{{ $enquiries->nextPageUrl() }}">Older →</a>@endif
  </nav>
</div>
</body>
</html>
