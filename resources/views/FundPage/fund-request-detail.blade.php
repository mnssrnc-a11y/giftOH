@extends('layouts.dashboard')

@section('title', 'Fund Request Details - Gift of Hope')

@section('content')
	@php
		$status = $fundRequest['status_name'] ?? $fundRequest['status'] ?? 'Pending';
		$category = $fundRequest['category'] ?? $fundRequest['category_name'] ?? 'N/A';
		$documents = [
			['label' => 'Supporting Document', 'path' => $fundRequest['doc_image'] ?? null],
			['label' => 'Valid ID', 'path' => $fundRequest['id_image'] ?? null],
			['label' => 'Financial Report', 'path' => $fundRequest['financial_rprt'] ?? null],
			['label' => 'Barangay Clearance', 'path' => $fundRequest['barangay_clr'] ?? null],
		];
	@endphp

	<div class="min-h-screen bg-gray-50">
		<div class="border-b border-gray-200 bg-white px-8 py-4">
			<div class="mx-auto flex max-w-5xl items-center justify-between">
				<div>
					<h1 class="text-2xl font-bold text-gray-900">Fund Request Details</h1>
					<p class="text-sm text-gray-500">Submitted request information</p>
				</div>
				<a href="{{ route('dashboarduser') }}" class="text-sm font-semibold text-blue-600 hover:underline">Back to dashboard</a>
			</div>
		</div>

		<div class="mx-auto max-w-5xl p-8">
			<div class="mb-6 flex items-center justify-between rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
				<div>
					<p class="text-sm text-gray-500">Request ID</p>
					<p class="font-semibold text-gray-900">{{ $fundRequest['id'] ?? 'N/A' }}</p>
				</div>
				<span class="rounded-full bg-gray-100 px-4 py-2 text-sm font-semibold capitalize text-gray-700">{{ $status }}</span>
			</div>

			<div class="rounded-xl border border-gray-100 bg-white p-8 shadow-sm">
				<h2 class="mb-6 text-xl font-bold text-gray-900">Request Information</h2>
				<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
					<div><p class="text-sm font-semibold text-gray-500">Organization Name</p><p class="mt-1 text-gray-900">{{ $fundRequest['org_name'] ?? 'N/A' }}</p></div>
					<div><p class="text-sm font-semibold text-gray-500">Amount Requested</p><p class="mt-1 text-gray-900">₱{{ number_format((float) ($fundRequest['amount_requested'] ?? 0), 2) }}</p></div>
					<div><p class="text-sm font-semibold text-gray-500">Category</p><p class="mt-1 text-gray-900">{{ $category }}</p></div>
					<div><p class="text-sm font-semibold text-gray-500">Contact Person</p><p class="mt-1 text-gray-900">{{ $fundRequest['contact_person'] ?? 'N/A' }}</p></div>
					<div><p class="text-sm font-semibold text-gray-500">Contact Email</p><p class="mt-1 text-gray-900">{{ $fundRequest['contact_email'] ?? 'N/A' }}</p></div>
					<div><p class="text-sm font-semibold text-gray-500">Contact Number</p><p class="mt-1 text-gray-900">{{ $fundRequest['contact_phone'] ?? $fundRequest['phone'] ?? 'N/A' }}</p></div>
					<div class="md:col-span-2"><p class="text-sm font-semibold text-gray-500">Tax ID</p><p class="mt-1 text-gray-900">{{ $fundRequest['tax_id'] ?? 'N/A' }}</p></div>
					<div class="md:col-span-2"><p class="text-sm font-semibold text-gray-500">Address</p><p class="mt-1 text-gray-900">{{ $fundRequest['address'] ?? 'N/A' }}</p></div>
					<div class="md:col-span-2"><p class="text-sm font-semibold text-gray-500">Mission</p><p class="mt-1 whitespace-pre-line text-gray-900">{{ $fundRequest['mission'] ?? 'N/A' }}</p></div>
				</div>

				<div class="mt-8 border-t border-gray-200 pt-6">
					<h3 class="mb-4 text-lg font-bold text-gray-900">Submitted Documents</h3>
					<div class="grid grid-cols-1 gap-3 md:grid-cols-2">
						@foreach ($documents as $document)
							@php
								$path = $document['path'];
								$extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : null;
								$previewUrl = $path ? asset('storage/' . ltrim($path, '/')) : null;
								$isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
							@endphp
							<div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
								<p class="mb-3 text-sm font-semibold text-gray-700">{{ $document['label'] }}</p>
								@if ($path)
									@if ($isImage)
										<img src="{{ $previewUrl }}" alt="{{ $document['label'] }}" class="h-48 w-full rounded-md border border-gray-200 bg-white object-contain p-2">
									@else
										<div class="flex h-48 items-center justify-center rounded-md border border-gray-200 bg-white">
											<a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="text-sm font-semibold text-blue-600 hover:underline">Open {{ strtoupper($extension ?? 'file') }} document</a>
										</div>
									@endif
								@else
									<div class="flex h-48 items-center justify-center rounded-md border border-dashed border-gray-300 bg-white text-sm text-gray-400">Not provided</div>
								@endif
							</div>
						@endforeach
					</div>
				</div>
			</div>
		</div>
	</div>

@endsection
