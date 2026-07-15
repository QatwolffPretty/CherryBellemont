<x-layouts.admin title="Collection admin | Cherry Bellemont">
    <x-admin.section>
        <x-admin.page-header eyebrow="Catalogue" title="Collection">
            <x-slot:actions>
                <x-admin.button variant="outline" :href="route('admin.products.index', ['low_stock' => 1])">Low stock</x-admin.button>
                <x-admin.button :href="route('admin.products.create')">Add piece</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        @if(session('success'))<p class="mt-6 border border-gold/50 p-4 text-gold">{{ session('success') }}</p>@endif

        <x-admin.table class="mt-8">
            <x-slot:head>
                <tr><th>Piece</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr>
            </x-slot:head>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>RM {{ number_format($product->price, 2) }}</td>
                    <td><x-admin.badge :status="$product->stock <= 5 ? 'low_stock' : 'in_stock'" :label="$product->stock" /></td>
                    <td><x-admin.badge :status="$product->status" /></td>
                    <td class="text-right">
                        <x-admin.button variant="outline" :href="route('admin.products.edit', $product)">Edit</x-admin.button>
                        <form class="ml-3 inline" method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Remove this piece from the collection?')">
                            @csrf
                            @method('DELETE')
                            <x-admin.button variant="danger" type="submit">Delete</x-admin.button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty-state title="No products match this view." description="Add a new piece or adjust the selected catalogue filter." icon="bi-box-seam" /></td></tr>
            @endforelse
        </x-admin.table>

        <div class="mt-8">{{ $products->links() }}</div>
    </x-admin.section>
</x-layouts.admin>
