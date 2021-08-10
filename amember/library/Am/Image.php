<?php

class Am_Image
{
    const RESIZE_CROP = 'crop';
    const RESIZE_GIZMO = 'gizmo';
    const RESIZE_FITWIDTH = 'fit_width';
    const RESIZE_FITHEIGHT = 'fit_height';
    const RESIZE_FIT = 'fit';
    const FILL_COLOR = 0xCCCCCC;
    const FILL_TRANSPARENT = 'transparent';

    protected $handler = null;

    public function __construct($path, $mime = null)
    {
        if (is_null($mime)) {
            $mime = Upload::getMimeType($path);
        }

        switch ($mime) {
            case 'image/gif' :
                $handler = imagecreatefromgif($path);
                break;
            case 'image/png' :
                $handler = imagecreatefrompng($path);
                break;
            case 'image/jpeg' :
                $handler = imagecreatefromjpeg($path);
                $handler = $this->fixOrientation($handler, $path);
                break;
            default :
                throw new Am_Exception_InternalError(sprintf('Unknown MIME type [%s]', $mime));
        }
        if (false === $handler)
            throw new Am_Exception_InternalError(sprintf('Can not open [%s] as image resource', $path));

        $this->handler = $handler;
    }

    protected function fixOrientation($handler, $path)
    {
        if (function_exists('exif_read_data')) {
            $exif = exif_read_data($path);

            if (isset($exif['Orientation']))
            {
                switch ($exif['Orientation'])
                {
                    case 3:
                        $handler = imagerotate($handler, 180, 0);
                        break;
                    case 6:
                        $handler = imagerotate($handler, -90, 0);
                        break;
                    case 8:
                        $handler = imagerotate($handler, 90, 0);
                        break;
                }
            }
        }
        return $handler;
    }

    public function __destruct()
    {
        imagedestroy($this->handler);
    }

    public function color($red, $green, $blue, $alpha = 0)
    {
        return $alpha ?
            imagecolorallocatealpha($this->handler, $red, $green, $blue, $alpha) :
            imagecolorallocate($this->handler, $red, $green, $blue);
    }

    public function width()
    {
        return imagesx($this->handler);
    }

    public function height()
    {
        return imagesy($this->handler);
    }

    public function textWidth($size, $angle, $fontfile, $text)
    {
        $r = imagettfbbox($size, $angle, $fontfile, $text);
        return $r[4];
    }

    public function textHeight($size, $angle, $fontfile, $text)
    {
        $r = imagettfbbox($size, $angle, $fontfile, $text);
        return $r[1];
    }

    public function text($size, $angle, $x, $y, $color, $fontfile, $text)
    {
        imagettftext($this->handler, $size, $angle, $x, $y, $color, $fontfile, $text);
        return $this;
    }

    public function resize($width, $height, $resize_type = self::RESIZE_CROP, $fill_color = self::FILL_COLOR)
    {
        $src_height = imagesy($this->handler);
        $src_width = imagesx($this->handler);

        if ($resize_type == self::RESIZE_FIT) {
            $resize_type = $src_height > $src_width ? self::RESIZE_FITHEIGHT : self::RESIZE_FITWIDTH;
        }

        switch ($resize_type) {
            case self::RESIZE_GIZMO:
                $q = min($width / $src_width, $height / $src_height);
                break;
            case self::RESIZE_CROP:
                $q = max($width / $src_width, $height / $src_height);
                break;
            case self::RESIZE_FITWIDTH:
                $q = $width / $src_width;
                break;
            case self::RESIZE_FITHEIGHT:
                $q = $height / $src_height;
                break;
            default:
                throw new Am_Exception_InternalError(sprintf('Unknown resize type [%s] in %s->%s', $resize_type, __CLASS__, __METHOD__));
        }

        $n_width = $src_width * $q;
        $n_height = $src_height * $q;

        if ($resize_type == self::RESIZE_FITHEIGHT) {
            $width = $n_width;
        }
        if ($resize_type == self::RESIZE_FITWIDTH) {
            $height = $n_height;
        }

        $dist_x = $dist_y = 0;

        if ($n_width < $width) {
            $dist_x = floor(($width - $n_width) / 2);
        } else {
            $dist_x = -1 * floor(($n_width - $width) / 2);
        }

        if ($n_height < $height) {
            $dist_y = floor(($height - $n_height) / 2);
        } else {
            $dist_y = -1 * floor(($n_height - $height) / 2);
        }

        $result_handler = imagecreatetruecolor($width, $height);
        if ($fill_color == self::FILL_TRANSPARENT) {
            imagealphablending($result_handler, false);
            imagesavealpha($result_handler, true);
            $transparent = imagecolorallocatealpha($result_handler, 255, 255, 255, 127);
            imagefilledrectangle($result_handler, 0, 0, $width, $height, $transparent);
        } else {
            imagefilledrectangle($result_handler, 0, 0, $width, $height, $fill_color);
        }
        imagecopyresampled($result_handler, $this->handler, $dist_x, $dist_y, 0, 0, $n_width, $n_height, $src_width, $src_height);
        imagedestroy($this->handler);
        $this->handler = $result_handler;
        return $this;
    }

    public function rotate($angle, $fill_color=self::FILL_COLOR)
    {
        $this->handler = imagerotate($this->handler, -1 * $angle, $fill_color);
        return $this;
    }

    public function save($filename, $mime = 'image/jpeg')
    {
        switch ($mime) {
            case 'image/gif' :
                imagegif($this->handler, $filename);
                break;
            case 'image/png' :
                imagepng($this->handler, $filename);
                break;
            case 'image/jpeg' :
                imagejpeg($this->handler, $filename, 95);
                break;
            default :
                throw new Am_Exception_InternalError(sprintf('Unknown MIME type [%s]', $mime));
        }

        return $this;
    }

    public function flush($mime = 'image/jpeg')
    {
        $this->save(null, $mime);
        return $this;
    }

    public function data($mime = 'image/jpeg')
    {
        ob_start();
        $this->save(null, $mime);
        return ob_get_clean();
    }
}