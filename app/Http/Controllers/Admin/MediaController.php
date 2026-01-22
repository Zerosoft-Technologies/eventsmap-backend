<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Upload a media file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // Max 10MB
            'folder' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $folder = $request->input('folder');
            
            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Store the file
            $path = $file->storeAs($folder, $filename, 'public');
            
            // Get file info
            $mimeType = $file->getMimeType();
            $size = $file->getSize();
            
            // Determine file type
            $fileType = $this->getFileType($mimeType);
            
            return response()->json([
                'message' => 'File uploaded successfully',
                'data' => [
                    'id' => Str::uuid(),
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => url('storage/' . $path),
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'file_type' => $fileType,
                    'folder' => $folder,
                    'created_at' => now()->toISOString(),
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a media file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $path = $request->input('path');
            
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                
                return response()->json([
                    'message' => 'File deleted successfully'
                ]);
            }
            
            return response()->json([
                'message' => 'File not found'
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * List files in a folder
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'folder' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $folder = $request->input('folder');
            $files = Storage::disk('public')->files($folder);
            $directories = Storage::disk('public')->directories($folder);
            
            $fileList = [];
            foreach ($files as $file) {
                $fileInfo = pathinfo($file);
                $mimeType = Storage::disk('public')->mimeType($file);
                $size = Storage::disk('public')->size($file);
                
                $fileList[] = [
                    'filename' => $fileInfo['basename'],
                    'path' => $file,
                    'url' => url('storage/' . $file),
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'file_type' => $this->getFileType($mimeType),
                    'last_modified' => Storage::disk('public')->lastModified($file),
                ];
            }
            
            return response()->json([
                'message' => 'Files retrieved successfully',
                'data' => [
                    'files' => $fileList,
                    'directories' => $directories,
                    'folder' => $folder,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve files',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Determine file type from MIME type
     *
     * @param string $mimeType
     * @return string
     */
    private function getFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (str_contains($mimeType, 'pdf')) {
            return 'pdf';
        } elseif (str_contains($mimeType, 'document') || str_contains($mimeType, 'text')) {
            return 'document';
        } else {
            return 'other';
        }
    }
}
