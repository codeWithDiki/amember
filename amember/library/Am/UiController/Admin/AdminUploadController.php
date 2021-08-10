<?php
/*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin Info / PHP
*    FileName $RCSfile$
*    Release: 6.3.6 ($Revision: 4883 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class_exists('Am_Form', true);

class AdminUploadController extends Am_Mvc_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return true;
    }

    public function gridAction()
    {
        $prefix = $this->getRequest()->getParam('prefix');
        $secure = $this->getRequest()->getParam('secure');
        if (!$prefix) {
            throw new Am_Exception_InputError('prefix is undefined');
        }
        if (!$this->getDi()->uploadAcl->checkPermission($prefix,
                    Am_Upload_Acl::ACCESS_LIST,
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }

        if (in_array($prefix, ['product-img-cart', 'downloads', 'video', 'video-poster', 'personal-content', 'softsale'])) {
            list($storageId, $path, $query) = $this->getDi()->plugins_storage->splitPath($this->_request->get('path', 'upload::'));
            $storagePlugins = $this->getDi()->plugins_storage->loadEnabled()->getAllEnabled();
        } else {
            list($storageId, $path, $query) = $this->getDi()->plugins_storage->splitPath($this->_request->get('path', 'upload::'));
            $storageId = 'upload';
            $path = 'upload::';
            $storagePlugins = [$this->getDi()->plugins_storage->loadGet($storageId)];
        }

        $storage = $this->getDi()->plugins_storage->loadGet($storageId);
        $storage->setPrefix($prefix);
        $grid = new Am_Storage_Grid($storage, $this->_request, $this->_response, $storagePlugins);
        $grid->setSecure($secure);
        if ($query) {
            $grid->action($query, $path, $this->view);
        } else {
            $grid->render($path, $this->view);
        }
    }

    public function getAction()
    {
        if (!$file = $this->getDi()->plugins_storage->getFile($this->getParam('id'))) {
            throw new Am_Exception_InputError("Can not fetch file for id: ".Am_Html::escape($this->getParam('id')));
        }
//        @todo detect if file is upload and then check permissions
//        if (!$this->getDi()->uploadAcl->checkPermission($file,
//                    Am_Upload_Acl::ACCESS_READ,
//                    $this->getDi()->authAdmin->getUser())) {
//            throw new Am_Exception_AccessDenied();
//        }

        if ($path = $file->getLocalPath()) {
            $this->_helper->sendFile($path, $file->getMime(),
                ['filename' => $file->getName()]);
        } else {
            $this->_response->redirectLocation($file->getUrl(600));
        }
        exit;
    }

    public function reUploadAction()
    {
        $file = $this->getDi()->uploadTable->load($this->getParam('id'));
        if (!$this->getDi()->uploadAcl->checkPermission($file,
                    Am_Upload_Acl::ACCESS_WRITE,
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }

        $upload = new Am_Upload($this->getDi());

        try {
            $upload->processReSubmit('upload', $file);

            if ($file->isValid()) {
                $data = [
                    'ok' => true,
                    'name' => $file->getName(),
                    'filename' => $file->getFilename(),
                    'size_readable' => $file->getSizeReadable(),
                    'upload_id' => $file->pk(),
                    'mime' => $file->mime
                ];
            } else {
                $data = [
                    'ok' => false,
                    'error' => 'No files uploaded',
                ];
            }
        } catch (Am_Exception $e) {
                $data = [
                    'ok' => false,
                    'error' => 'No files uploaded',
                ];
                $this->getDi()->logger->error("Error in admin file re-upload {upload}", ["exception" => $e, 'upload' => $upload]);
        }
        echo json_encode($data);
    }

    public function uploadAction()
    {
        if (!$this->getDi()->uploadAcl->checkPermission($this->getParam('prefix'),
                    Am_Upload_Acl::ACCESS_WRITE,
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }

        $secure = $this->getParam('secure', false);

        $upload = new Am_Upload($this->getDi());
        $upload->setPrefix($this->getParam('prefix'));
        $upload->processSubmit('upload');
        //find currently uploaded file
        list($file) = $upload->getUploads();

        try {
            $data = [
                'ok' => true,
                'name' => $file->getName(),
                'size_readable' => $file->getSizeReadable(),
                'upload_id' => $secure ?  Am_Form_Element_Upload::signValue($file->pk()) : $file->pk(),
                'mime' => $file->mime
            ];
        } catch (Am_Exception $e) {
            $data = [
                'ok' => false,
                'error' => 'No files uploaded',
            ];
            $this->getDi()->logger->error("Error in admin upload {upload}", ["exception" => $e, 'upload' => $upload]);
        }
        echo json_encode($data);
    }

    public function getSizeAction()
    {
        $file = $this->getDi()->uploadTable->load($this->getParam('id'));

        if (!$file) {
            throw new Am_Exception_InputError(
            'Can not fetch file for id : ' . Am_Html::escape($this->getParam('id'))
            );
        }

        if (!$this->getDi()->uploadAcl->checkPermission($file,
                    Am_Upload_Acl::ACCESS_READ,
                    $this->getDi()->authAdmin->getUser())) {
            throw new Am_Exception_AccessDenied();
        }

        if ($size = getimagesize($file->getFullPath()) ) {
            echo json_encode(
                [
                    'width' => $size[0],
                    'height' => $size[1]
                ]
            );
        } else {
            echo json_encode(false);
        }
    }
}