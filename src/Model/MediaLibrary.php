<?php
/**
 * Phire Media Module
 *
 * @link       https://github.com/phirecms/phire-media
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.phirecms.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Phire\Media\Model;

use Phire\Media\Table;
use Phire\Model\AbstractModel;
use Pop\File\Dir;

/**
 * Media Library Model class
 *
 * @category   Phire\Media
 * @package    Phire\Media
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2016 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.phirecms.org/license     New BSD License
 * @version    1.0.0
 */
class MediaLibrary extends AbstractModel
{

    /**
     * Get all libraries
     *
     * @param  int    $limit
     * @param  int    $page
     * @param  string $sort
     * @return array
     */
    public function getAll($limit = null, $page = null, $sort = null)
    {
        $order = (null !== $sort) ? $this->getSortOrder($sort, $page) : 'order ASC';

        if (null !== $limit) {
            $page = ((null !== $page) && ((int)$page > 1)) ?
                ($page * $limit) - $limit : null;

            return Table\MediaLibraries::findAll([
                'offset' => $page,
                'limit'  => $limit,
                'order'  => $order
            ])->rows();
        } else {
            return Table\MediaLibraries::findAll([
                'order'  => $order
            ])->rows();
        }
    }

    /**
     * Get library by ID
     *
     * @param  int $id
     * @return void
     */
    public function getById($id)
    {
        $library = Table\MediaLibraries::findById($id);
        if (isset($library->id)) {
            $data = $library->getColumns();
            $data['max_filesize'] = $this->unparseMaxFilesize($data['max_filesize']);

            if (null !== $data['actions']) {
                $actions = unserialize($data['actions']);
                $keys    = array_keys($actions);
                $values  = array_values($actions);
                if (isset($keys[0]) && isset($values[0])) {
                    $data['action_name_1']    = $keys[0];
                    $data['action_method_1']  = $values[0]['method'];
                    $data['action_params_1']  = $values[0]['params'];
                    $data['action_quality_1'] = $values[0]['quality'];
                }
                $data['actions'] = $actions;
            }

            $this->data = array_merge($this->data, $data);
        }
    }

    /**
     * Get library by folder
     *
     * @param  string $folder
     * @return void
     */
    public function getByFolder($folder)
    {
        $library = Table\MediaLibraries::findBy(['folder' => $folder]);
        if (isset($library->id)) {
            $data = $library->getColumns();
            $data['max_filesize'] = $this->unparseMaxFilesize($data['max_filesize']);

            if (null !== $data['actions']) {
                $actions = unserialize($data['actions']);
                $keys    = array_keys($actions);
                $values  = array_values($actions);
                if (isset($keys[0]) && isset($values[0])) {
                    $data['action_name_1']    = $keys[0];
                    $data['action_method_1']  = $values[0]['method'];
                    $data['action_params_1']  = $values[0]['params'];
                    $data['action_quality_1'] = $values[0]['quality'];
                }
                $data['actions'] = $actions;
            }

            $this->data = array_merge($this->data, $data);
        }
    }

    /**
     * Save new library
     *
     * @param  array $fields
     * @return void
     */
    public function save(array $fields)
    {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $fields['folder'])) {
            $this->createFolder($fields['folder']);
        }
        $library = new Table\MediaLibraries([
            'name'             => $fields['name'],
            'folder'           => $fields['folder'],
            'allowed_types'    => $fields['allowed_types'],
            'disallowed_types' => $fields['disallowed_types'],
            'max_filesize'     => $this->parseMaxFilesize($fields['max_filesize']),
            'actions'          => serialize($this->parseActions($fields['folder'])),
            'adapter'          => $fields['adapter'],
            'order'            => (int)$fields['order'],
        ]);
        $library->save();

        $this->data = array_merge($this->data, $library->getColumns());
    }

    /**
     * Update an existing library
     *
     * @param  array $fields
     * @return void
     */
    public function update(array $fields)
    {
        $library = Table\MediaLibraries::findById($fields['id']);
        if (isset($library->id)) {
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $fields['folder'])) {
                $this->createFolder($fields['folder']);
            }
            $library->name             = $fields['name'];
            $library->folder           = $fields['folder'];
            $library->allowed_types    = $fields['allowed_types'];
            $library->disallowed_types = $fields['disallowed_types'];
            $library->max_filesize     = $this->parseMaxFilesize($fields['max_filesize']);
            $library->actions          = serialize($this->parseActions($fields['folder']));
            $library->adapter          = $fields['adapter'];
            $library->order            = (int)$fields['order'];
            $library->save();

            $this->data = array_merge($this->data, $library->getColumns());
        }
    }

    /**
     * Remove a library
     *
     * @param  array $fields
     * @return void
     */
    public function process(array $fields)
    {
        if (isset($fields['rm_media_libraries'])) {
            foreach ($fields['rm_media_libraries'] as $id) {
                $library = Table\MediaLibraries::findById((int)$id);
                if (isset($library->id)) {
                    $dir = new Dir($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . '/' . $library->folder);
                    $dir->emptyDir(true);
                    $library->delete();
                }
            }
        } else if (isset($fields['process_media_libraries'])) {
            foreach ($fields['process_media_libraries'] as $id) {
                $library = new self();
                $library->getById((int)$id);
                if (isset($library->id)) {
                    $class   = 'Pop\Image\\' .  $library->adapter;
                    $formats = array_keys($class::getFormats());
                    $media   = Table\Media::findBy(['library_id' => $library->id]);
                    if ($media->count() > 0) {
                        $med = new Media();
                        foreach ($media->rows() as $m) {
                            $fileParts = pathinfo($m->file);
                            if (!empty($fileParts['extension']) && in_array($fileParts['extension'], $formats)) {
                                $med->processImage($m->file, $library);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * create library folder
     *
     * @param  string $folder
     * @return boolean
     */
    public function createFolder($folder)
    {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder)) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder);
            chmod($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder, 0777);
            copy(
                $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . 'index.html',
                $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'index.html'
            );
        }
    }

    /**
     * Determine if list of libraries has pages
     *
     * @param  int $limit
     * @return boolean
     */
    public function hasPages($limit)
    {
        return (Table\MediaLibraries::findAll()->count() > $limit);
    }

    /**
     * Get count of libraries
     *
     * @return int
     */
    public function getCount()
    {
        return Table\MediaLibraries::findAll()->count();
    }

    /**
     * Get library settings
     *
     * @return array
     */
    public function getSettings()
    {
        return [
            'folder'           => $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $this->folder,
            'allowed_types'    => (!empty($this->allowed_types) ? explode(',', $this->allowed_types) : []),
            'disallowed_types' => (!empty($this->disallowed_types) ? explode(',', $this->disallowed_types) : []),
            'max_filesize'     => $this->parseMaxFilesize($this->max_filesize)
        ];
    }

    /**
     * Get max filesize between library max filesize and PHP's max upload size
     *
     * @return string
     */
    public function getMaxFilesize()
    {
        if (!isset($this->max_filesize)) {
            return ini_get('upload_max_filesize');
        } else {
            $sysMax = $this->parseMaxFilesize($this->max_filesize);
            $phpMax = $this->parseMaxFilesize(ini_get('upload_max_filesize'));
            return ($sysMax <= $phpMax) ?
                $this->unparseMaxFilesize($sysMax) :
                $this->unparseMaxFilesize($phpMax);
        }
    }

    /**
     * Parse max filesize
     *
     * @param  string $size
     * @return int
     */
    public function parseMaxFilesize($size)
    {
        $size = strtolower($size);

        if (stripos($size, 'm') !== false) {
            $size = (int)trim(substr($size, 0, strpos($size, 'm'))) * 1000000;
        } else if (stripos($size, 'k') !== false) {
            $size = (int)trim(substr($size, 0, strpos($size, 'k'))) * 1000;
        }

        return $size;
    }

    /**
     * Un-parse max filesize
     *
     * @param  int $size
     * @return string
     */
    public function unparseMaxFilesize($size)
    {
        if ($size >= 1000000) {
            $size = floor($size / 1000000) . 'M';
        } else if ($size >= 1000) {
            $size = floor($size / 1000) . 'K';
        }

        return $size;
    }

    /**
     * Parse actions
     *
     * @param  string $folder
     * @return array
     */
    protected function parseActions($folder)
    {
        $dir     = new Dir($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder);
        $curDirs = [];
        $newDirs = [];
        foreach ($dir->getFiles() as $file) {
            if (is_dir($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $file)) {
                $curDirs[] = $file;
            }
        }

        $actions = [];

        foreach ($_POST as $key => $value) {
            if (substr($key, 0, 12) == 'action_name_') {
                $id = substr($key, (strrpos($key, '_')  + 1));
                if (!empty($_POST['action_name_' . $id]) && ($_POST['action_method_' . $id] != '----') &&
                    !empty($_POST['action_params_' . $id])) {
                    $actions[$_POST['action_name_' . $id]] = [
                        'method'  => $_POST['action_method_' . $id],
                        'params'  => $_POST['action_params_' . $id],
                        'quality' => (!empty($_POST['action_quality_' . $id]) ? (int)$_POST['action_quality_' . $id] : 80)
                    ];

                    if (!file_exists($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $_POST['action_name_' . $id])) {
                        mkdir($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $_POST['action_name_' . $id]);
                        chmod($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $_POST['action_name_' . $id], 0777);
                        copy(
                            $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . 'index.html',
                            $_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $_POST['action_name_' . $id] . DIRECTORY_SEPARATOR . 'index.html'
                        );
                    }
                    $newDirs[] = $_POST['action_name_' . $id];
                }
            }
        }

        // Clean up directories
        foreach ($curDirs as $dir) {
            if (!in_array($dir, $newDirs)) {
                $d = new Dir($_SERVER['DOCUMENT_ROOT'] . BASE_PATH . CONTENT_PATH . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $dir);
                $d->emptyDir(true);
            }
        }


        return $actions;
    }

}
