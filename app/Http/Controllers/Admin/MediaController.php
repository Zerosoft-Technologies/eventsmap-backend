<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\MediaHelper;
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
            
            // Generate unique identifier and filename
            $fileId = Str::uuid();
            $filename = $fileId . '.' . $file->getClientOriginalExtension();
            
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
                    'id' => $fileId,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'url' => MediaHelper::url($path),
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
     * List media files with pagination and optional filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'folder' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 24);
            $search = $request->input('search');
            $folder = $request->input('folder');
            
            // Get all files from storage
            $allFiles = Storage::disk('public')->allFiles();
            
            // Filter by folder if specified
            if ($folder) {
                $allFiles = array_filter($allFiles, function($file) use ($folder) {
                    return str_starts_with($file, $folder . '/');
                });
            }
            
            // Filter by search term if specified
            if ($search) {
                $allFiles = array_filter($allFiles, function($file) use ($search) {
                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    return str_contains($filename, $search) || str_contains($extension, $search);
                });
            }
            
            // Sort files by name
            sort($allFiles);
            
            // Calculate pagination
            $total = count($allFiles);
            $offset = ($page - 1) * $perPage;
            $paginatedFiles = array_slice($allFiles, $offset, $perPage);
            
            // Build file list with metadata
            $fileList = [];
            foreach ($paginatedFiles as $file) {
                $fileInfo = pathinfo($file);
                $mimeType = Storage::disk('public')->mimeType($file);
                $size = Storage::disk('public')->size($file);
                
                $fileList[] = [
                    'filename' => $fileInfo['basename'],
                    'path' => $file,
                    'url' => MediaHelper::url($file),
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'file_type' => $this->getFileType($mimeType),
                    'last_modified' => Storage::disk('public')->lastModified($file),
                ];
            }
            
            // Build pagination metadata
            $lastPage = ceil($total / $perPage);
            $pagination = [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? $offset + count($paginatedFiles) : null,
            ];
            
            return response()->json([
                'message' => 'Media files retrieved successfully',
                'data' => $fileList,
                'meta' => [
                    'pagination' => $pagination,
                    'filters' => [
                        'search' => $search,
                        'folder' => $folder,
                    ],
                ],
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
