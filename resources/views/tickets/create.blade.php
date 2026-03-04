@extends('layouts.app')

@section('title', 'Novo ticket')

@section('content')
<div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">Abrir novo ticket</h1>

    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="space-y-5 bg-white border border-slate-200 rounded-lg p-6">
        @csrf

        <div>
            <label for="subject" class="block text-sm font-medium text-slate-700 mb-1">Assunto</label>
            <input id="subject" name="subject" type="text" required maxlength="255"
                value="{{ old('subject') }}"
                class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            @error('subject')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Descrição</label>
            <textarea id="description" name="description" rows="8" required
                class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('description') }}</textarea>
            @error('description')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="due_at" class="block text-sm font-medium text-slate-700 mb-1">Prazo de entrega (opcional)</label>
            <input id="due_at" name="due_at" type="date" value="{{ old('due_at') }}"
                class="w-48 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            @error('due_at')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="priority" class="block text-sm font-medium text-slate-700 mb-1">Prioridade (opcional)</label>
            <select id="priority" name="priority" class="w-48 px-3 py-2 border border-slate-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Padrão</option>
                <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Baixa</option>
                <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>Alta</option>
                <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgente</option>
            </select>
            @error('priority')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="attachments" class="block text-sm font-medium text-slate-700 mb-1">Anexos (opcional)</label>
            <input id="attachments" name="attachments[]" type="file" multiple
                class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm file:mr-4 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
            <p class="text-xs text-slate-500 mt-1">Até 50MB por arquivo.</p>
            @error('attachments')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
            @error('attachments.*')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 rounded-md text-white bg-blue-600 hover:bg-blue-700">Criar ticket</button>
            <a href="{{ route('tickets.index') }}" class="px-4 py-2 rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50">Cancelar</a>
        </div>
    </form>
</div>
@endsection
