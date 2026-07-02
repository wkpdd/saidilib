@foreach (($pixels ?? collect()) as $pixel)
    @if ($pixel->provider === 'facebook')
        <noscript><img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id={{ $pixel->pixel_id }}&ev=PageView&noscript=1"/></noscript>
    @endif
@endforeach
