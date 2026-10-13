{{-- Its own form (the section's button points at it with form=…): a purge must not save the settings. --}}
<form id="cloudflare-purge-form" method="POST" action="{{ route('admin.settings.integrations.cloudflare-purge') }}">@csrf</form>
