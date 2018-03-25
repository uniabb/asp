<?php
/**
 * BF2Statistics ASP Framework
 *
 * Author:       Steven Wilson
 * Copyright:    Copyright (c) 2006-2018, BF2statistics.com
 * License:      GNU GPL v3
 *
 */
use System\Controller;
use System\IO\Directory;
use System\IO\File;
use System\IO\Path;
use System\View;

/**
 * Snapshots Module Controller
 *
 * @package Modules
 */
class Snapshots extends Controller
{
    /**
     * @var SnapshotsModel
     */
    protected $snapshotsModel;

    /**
     * @protocol    GET
     * @request     /ASP/snapshots
     * @output      html
     */
    public function index()
    {
        // Load model
        $this->loadModel('SnapshotsModel', 'snapshots');

        // Load view
        $view = new View('index', 'snapshots');
        $view->set('snapshots', $this->snapshotsModel->getSnapshots("unauthorized"));

        // Attach needed scripts for the form
        $view->attachScript("/ASP/frontend/js/datatables/jquery.dataTables.js");
        $view->attachScript("/ASP/frontend/modules/snapshots/js/index.js");

        // Attach needed stylesheets
        $view->attachStylesheet("/ASP/frontend/css/icons/icol16.css");

        // Send output
        $view->render();
    }

    /**
     * @protocol    POST
     * @request     /ASP/snapshots/accept
     * @output      json
     */
    public function postAccept()
    {
        // Ensure a valid action
        if ($_POST['action'] != 'process')
        {
            if (isset($_POST['ajax']))
                echo json_encode(['success' => false, 'message' => 'Invalid Action!']);
            else
                $this->index();

            return;
        }

        // Ensure we have a backup selected
        if (!isset($_POST['snapshot']))
        {
            $this->sendJsonResponse(false, 'No snapshots specified!');
            return;
        }

        $file = Path::Combine(SYSTEM_PATH, "snapshots", "unauthorized", $_POST['snapshot'] . '.json');
        if (!File::Exists($file))
        {
            $this->sendJsonResponse(false, 'No unauthorized snapshots with the filename exists: '. Path::GetFilename($file));
            return;
        }

        // Ensure that the directories we need are writable
        $path1 = Path::Combine(SYSTEM_PATH, "snapshots", "processed");
        $path2 = Path::Combine(SYSTEM_PATH, "snapshots", "failed");
        if (!Directory::IsWritable($path1) || !Directory::IsWritable($path2))
        {
            $this->sendJsonResponse(false, 'Not all snapshot directories are writable. Please Test your system configuration.');
            return;
        }

        try
        {
            // Load model, and call method
            $this->loadModel('SnapshotsModel', 'snapshots');
            $this->snapshotsModel->importSnapshot($file, $message);

            // Tell the client of the success
            $this->sendJsonResponse(true, $message);
        }
        catch (IOException $e)
        {
            $fileName = Path::GetFilename($file);
            $message = sprintf("Failed to process snapshot (%s)!\n\n%s", $fileName, $e->getMessage());
            $this->sendJsonResponse(false, $message);
        }
        catch (Exception $e)
        {
            $fileName = Path::GetFilename($file);
            try
            {
                // Move snapshot to failed
                $newPath = Path::Combine(SYSTEM_PATH, "snapshots", "failed", $fileName);
                File::Move($file, $newPath);
            }
            catch (Exception $ex)
            {
                // ignore
            }

            // Output message
            $message = sprintf("Failed to process snapshot (%s)!\n\n%s", $fileName, $e->getMessage());
            $this->sendJsonResponse(false, $message);
        }
    }

    /**
     * @protocol    POST
     * @request     /ASP/snapshots/delete
     * @output      json
     */
    public function postDelete()
    {
        // Ensure a valid action
        if ($_POST['action'] != 'delete')
        {
            if (isset($_POST['ajax']))
                $this->sendJsonResponse(false, 'Invalid Action!');
            else
                $this->index();

            return;
        }

        // Ensure we have a backup selected
        if (!isset($_POST['snapshots']))
        {
            $this->sendJsonResponse(false, 'No snapshots specified!');
            return;
        }

        $path = Path::Combine(SYSTEM_PATH, "snapshots", "unauthorized");

        try
        {
            foreach ($_POST['snapshots'] as $file)
            {
                $file = Path::Combine($path, $file . '.json');
                File::Delete($file);
            }

            $this->sendJsonResponse(true, 'Snapshots Removed.');
        }
        catch (Exception $e)
        {
            $this->sendJsonResponse(false, $e->getMessage());
        }
    }
}