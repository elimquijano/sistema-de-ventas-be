<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Maneja la subida de un archivo y lo guarda en el disco público.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:5120', // 5MB Max
            'path' => 'required|string|in:products,receipts,logos,avatars' // Validar carpetas permitidas
        ]);

        $file = $request->file('file');
        $path = $request->path;

        // Generar un nombre de archivo único para evitar colisiones
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Guardar el archivo en la carpeta especificada dentro del disco 'public'
        $storedPath = $file->storeAs($path, $filename, 'public');

        return response()->json([
            'message' => 'Archivo subido exitosamente',
            'path' => $storedPath, // Ruta relativa para guardar en la BD
            'url' => Storage::disk('public')->url($storedPath), // URL completa para el frontend
        ], 201);
    }
}
