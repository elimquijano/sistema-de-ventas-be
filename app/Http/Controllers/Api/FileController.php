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
        $extension = $file->getClientOriginalExtension();

        // Generar un nombre de archivo único
        $filename = Str::uuid() . '.' . (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp']) ? 'jpg' : $extension);
        $storedPath = "{$path}/{$filename}";

        if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp'])) {
            try {
                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $image = $manager->read($file);
                
                // Ajustar resolución según el tipo
                $width = ($path === 'logos' || $path === 'avatars') ? 400 : 1200;
                $image->scale(width: $width);
                
                $encoded = $image->toJpeg(80);
                Storage::disk('public')->put($storedPath, (string) $encoded);
            } catch (\Exception $e) {
                $storedPath = $file->storeAs($path, $filename, 'public');
            }
        } else {
            $storedPath = $file->storeAs($path, $filename, 'public');
        }

        return response()->json([
            'message' => 'Archivo subido exitosamente',
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
        ], 201);
    }
}
