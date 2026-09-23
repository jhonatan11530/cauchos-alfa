<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
    <channel>
        <title>Cauchos Alfa</title>
        <link>{{ url('/') }}</link>
        <description>Catálogo de cauchos, llantas y repuestos para motos y bicicletas.</description>
        @foreach($products as $product)
            <item>
                <g:id>{{ $product->id }}</g:id>
                <g:title><![CDATA[{{ $product->name }}]]></g:title>
                <g:description><![CDATA[{{ $product->description ?? 'Repuesto o llanta de alta calidad.' }}]]></g:description>
                <g:link>{{ route('site.catalog') }}?categoria={{ $product->category_id }}</g:link>
                @if(count($product->imageUrls()) > 0)
                <g:image_link>{{ $product->imageUrls()[0] }}</g:image_link>
                @endif
                <g:condition>new</g:condition>
                <g:availability>{{ $product->availability === 'agotado' ? 'out of stock' : 'in stock' }}</g:availability>
                @if($product->price)
                <g:price>{{ $product->price }} COP</g:price>
                @endif
                <g:brand><![CDATA[Cauchos Alfa]]></g:brand>
                <g:mpn><![CDATA[{{ $product->reference }}]]></g:mpn>
            </item>
        @endforeach
    </channel>
</rss>

