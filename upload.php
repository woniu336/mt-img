<?php
session_start();
require_once 'config.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'response' => ['code' => '1'],
        'message' => '请先登录'
    ]);
    exit;
}

header('Content-Type: application/json');

class MeituanUploader
{
    public $name = '美团图床';
    public $ver = '1.0';
    private $token;

    public function __construct() {
        $this->token = MEITUAN_TOKEN;
        if (empty($this->token)) {
            throw new Exception('请先在后台配置Token');
        }
    }

    private function extractIdFromUrl($url) {
        // 从美团图片URL中提取ID
        if (preg_match('/\/([^\/]+)$/', $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    public function submit($file_path)
    {
        try {
            if (!file_exists($file_path)) {
                throw new Exception("文件不存在: " . $file_path);
            }

            if (!is_readable($file_path)) {
                throw new Exception("文件不可读: " . $file_path);
            }

            $headers = array(
                'Accept: */*',
                'Accept-Encoding: gzip, deflate, br',
                'Accept-Language: zh-CN,zh;q=0.9',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
                'Content-Type: multipart/form-data; boundary=----WebKitFormBoundarywt1pMxJgab51elEB',
                'Host: pic-up.meituan.com',
                'Origin: https://czz.meituan.com',
                'Pragma: no-cache',
                'Referer: https://czz.meituan.com/',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'client-id: p5gfsvmw6qnwc45n000000000025bbf1',
                'token: ' . $this->token
            );

            // 构建 multipart/form-data 数据
            $postData = "------WebKitFormBoundarywt1pMxJgab51elEB\r\n";
            $postData .= 'Content-Disposition: form-data; name="file"; filename="' . basename($file_path) . "\"\r\n";
            $postData .= 'Content-Type: ' . mime_content_type($file_path) . "\r\n\r\n";
            $postData .= file_get_contents($file_path) . "\r\n";
            $postData .= "------WebKitFormBoundarywt1pMxJgab51elEB--\r\n";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://pic-up.meituan.com/extrastorage/new/video?isHttps=true');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('CURL错误: ' . curl_error($ch));
            }

            curl_close($ch);

            $json = json_decode($response, true);
            if ($json === null) {
                throw new Exception('JSON解析失败: ' . json_last_error_msg());
            }

            if ($json['success'] === true) {
                $remoteUrl = $json['data']['originalLink'];
                $imageId = $this->extractIdFromUrl($remoteUrl);
                
                // 如果成功提取到ID，则返回包含ID的结果
                return [
                    'local_path' => $file_path,
                    'remote_url' => $remoteUrl,
                    'original_filename' => $json['data']['originalFileName'],
                    'image_id' => $imageId
                ];
            } else {
                throw new Exception("上传失败: " . ($json['message'] ?? '未知错误'));
            }
        } catch (Exception $e) {
            error_log("美团图床上传错误: " . $e->getMessage());
            return false;
        }
    }
}

function convertToWebp($imagePath) {
    try {
        // 检查文件是否为图片
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            error_log("文件不是有效的图片: " . $imagePath);
            return false;
        }

        // 获取图片类型
        $mime = $imageInfo['mime'];
        
        // 如果已经是webp格式，直接返回
        if ($mime === 'image/webp') {
            $originalSize = filesize($imagePath);
            return [
                'path' => $imagePath,
                'compression_info' => [
                    'original_size' => $originalSize,
                    'compressed_size' => $originalSize,
                    'compression_ratio' => 0,
                    'settings' => [
                        'quality' => 'original',
                        'width' => $imageInfo[0],
                        'height' => $imageInfo[1]
                    ]
                ]
            ];
        }

        // 根据原图片类型创建图片资源
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($imagePath);
                break;
            default:
                error_log("不支持的图片格式: " . $mime);
                return false;
        }

        if (!$source) {
            error_log("创建图片资源失败");
            return false;
        }

        // 获取原图尺寸
        $width = imagesx($source);
        $height = imagesy($source);

        // 创建新的图片
        $newImage = imagecreatetruecolor($width, $height);

        // 处理透明通道
        if ($mime == 'image/png' || $mime == 'image/gif') {
            // 保持透明度
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            
            // 设置透明背景
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefilledrectangle($newImage, 0, 0, $width, $height, $transparent);
        }

        // 复制图片
        if (!imagecopyresampled($newImage, $source, 0, 0, 0, 0, $width, $height, $width, $height)) {
            error_log("复制图片失败");
            return false;
        }

        // 获取新的文件路径
        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $imagePath);

        // 根据原始图片类型和大小动态调整质量
        $originalSize = filesize($imagePath);
        $quality = 82; // 默认质量

        // PNG图片可能需要更高的质量来保持清晰度
        if ($mime === 'image/png') {
            $quality = 90;
        }

        // 对于小图片，提高质量
        if ($originalSize < 100 * 1024) { // 小于100KB
            $quality = 92;
        }

        // 转换为WebP
        if (!imagewebp($newImage, $webpPath, $quality)) {
            error_log("保存WebP失败");
            return false;
        }

        // 检查转换后的大小，如果比原图大，尝试调整质量
        $newSize = filesize($webpPath);
        if ($newSize > $originalSize) {
            // 逐步降低质量直到文件大小小于原图或达到最低质量
            for ($q = $quality - 5; $q >= 60; $q -= 5) {
                imagewebp($newImage, $webpPath, $q);
                $newSize = filesize($webpPath);
                if ($newSize <= $originalSize) {
                    $quality = $q;
                    break;
                }
            }
        }

        // 释放资源
        imagedestroy($source);
        imagedestroy($newImage);

        if (file_exists($webpPath) && filesize($webpPath) > 0) {
            $compressedSize = filesize($webpPath);
            
            // 无论大小如何，都删除原文件（非webp格式）
            if ($mime !== 'image/webp') {
                unlink($imagePath);
            }
            
            $compressionRatio = round(($originalSize - $compressedSize) / $originalSize * 100, 2);
            
            error_log("图片转换成功: {$imagePath} -> {$webpPath}");
            error_log("压缩率: {$compressionRatio}% (原始大小: " . round($originalSize/1024, 2) . "KB, 压缩后: " . round($compressedSize/1024, 2) . "KB)");
            
            return [
                'path' => $webpPath,
                'compression_info' => [
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                    'compression_ratio' => $compressionRatio,
                    'settings' => [
                        'quality' => $quality,
                        'width' => $width,
                        'height' => $height
                    ]
                ]
            ];
        } else {
            error_log("WebP文件写入失败或文件大小为0: " . $webpPath);
            return false;
        }
    } catch (Exception $e) {
        error_log("转换WebP失败: " . $e->getMessage());
        return false;
    }
}

function processImageAsync($localPath, $historyFile) {
    try {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        
        // 转换为WebP
        $result = convertToWebp($localPath);
        
        if ($result) {
            // 更新历史记录
            $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
            
            // 查找并更新最后一条记录
            if (!empty($history)) {
                $lastEntry = &$history[count($history) - 1];
                
                // 更新文件路径
                $lastEntry['local_path'] = $result['path'];
                $lastEntry['file_type'] = 'image/webp';
                $lastEntry['conversion_status'] = 'success';
                
                // 更新压缩信息
                if (isset($result['compression_info'])) {
                    // 使用实际文件大小更新原始大小
                    $result['compression_info']['original_size'] = $lastEntry['file_size'];
                    
                    // 获取转换后文件的实际大小
                    $compressedSize = filesize($result['path']);
                    $result['compression_info']['compressed_size'] = $compressedSize;
                    
                    // 重新计算压缩率
                    $compressionRatio = round(($lastEntry['file_size'] - $compressedSize) / $lastEntry['file_size'] * 100, 2);
                    $result['compression_info']['compression_ratio'] = $compressionRatio;
                    
                    $lastEntry['compression_info'] = $result['compression_info'];
                }
                
                file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
                
                error_log("历史记录已更新，压缩率: " . $compressionRatio . "%");
            }
        }
    } catch (Exception $e) {
        error_log("异步处理失败: " . $e->getMessage());
    }
}

function renameUploadedFile($originalPath, $imageId) {
    if (!file_exists($originalPath)) {
        throw new Exception("原文件不存在: {$originalPath}");
    }

    if (!$imageId) {
        throw new Exception("无法获取图片ID");
    }

    $dirPath = dirname($originalPath);
    $newPath = $dirPath . '/' . $imageId; // 移除扩展名

    if (rename($originalPath, $newPath)) {
        return $newPath;
    } else {
        throw new Exception("重命名失败");
    }
}

try {
    if (!isset($_FILES['Filedata'])) {
        throw new Exception('没有接收到文件');
    }

    $file = $_FILES['Filedata'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('文件上传失败，错误代码：' . $file['error']);
    }

    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        throw new Exception('文件大小超过限制');
    }

    $tempPath = $file['tmp_name'];
    
    // 创建按日期组织的目录结构
    $uploadDir = 'uploads/' . date('Y/m/d/');
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('创建上传目录失败');
        }
    }
    
    $fileInfo = pathinfo($file['name']);
    $newFileName = uniqid() . '_' . date('His') . '.' . $fileInfo['extension'];
    $targetPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($tempPath, $targetPath)) {
        throw new Exception('移动文件失败');
    }

    chmod($targetPath, 0644);

    // 使用美团图床上传器
    $uploader = new MeituanUploader();
    $result = $uploader->submit($targetPath);

    if ($result) {
        try {
            // 使用图片ID重命名文件
            if (isset($result['image_id'])) {
                $newPath = renameUploadedFile($targetPath, $result['image_id']);
                $result['local_path'] = $newPath;
            }

            // 记录上传历史
            $historyFile = 'uploads/history.json';
            $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
            
            $history[] = [
                'local_path' => $result['local_path'],
                'remote_url' => $result['remote_url'],
                'original_filename' => $result['original_filename'],
                'image_id' => $result['image_id'] ?? null,
                'upload_time' => date('Y-m-d H:i:s'),
                'file_size' => $file['size'],
                'file_type' => $file['type'],
                'conversion_status' => 'pending'
            ];
            
            file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));

            // 返回上传结果
            echo json_encode([
                'response' => ['code' => '0'],
                'data' => [
                    'url' => ['url' => $result['remote_url']],
                    'local_path' => $result['local_path'],
                    'image_id' => $result['image_id'] ?? null,
                    'conversion_status' => 'pending'
                ]
            ]);

            // 异步处理图片转换
            processImageAsync($result['local_path'], $historyFile);

        } catch (Exception $e) {
            error_log("文件处理失败: " . $e->getMessage());
            echo json_encode([
                'response' => ['code' => '0'],
                'data' => [
                    'url' => ['url' => $result['remote_url']],
                    'local_path' => $result['local_path'],
                    'error' => $e->getMessage()
                ]
            ]);
        }
    } else {
        throw new Exception('上传到图床失败');
    }

} catch (Exception $e) {
    echo json_encode([
        'response' => ['code' => '1'],
        'message' => $e->getMessage()
    ]);
}
?>