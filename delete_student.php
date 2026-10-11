<?php
// delete_student.php - Secure Student Record Deletion with Profile Picture & File Cleanup
require_once 'auth_check.php';
require_admin(); // Enforce Admin Only Access
require_once 'db_connection.php';
require_once 'file_helper.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" || isset($_GET['id'])) {
    $id = intval($_POST['id'] ?? ($_GET['id'] ?? 0));

    if ($id > 0) {
        // Query to find existing profile picture and record file to delete from disk
        $fileQuery = $conn->prepare("SELECT profile_picture, record_file FROM students WHERE id = ?");
        if ($fileQuery) {
            $fileQuery->bind_param("i", $id);
            $fileQuery->execute();
            $fileRes = $fileQuery->get_result();
            if ($fileRes && $row = $fileRes->fetch_assoc()) {
                if (!empty($row['profile_picture'])) {
                    delete_record_file($row['profile_picture']);
                }
                if (!empty($row['record_file'])) {
                    delete_record_file($row['record_file']);
                }
            }
            $fileQuery->close();
        }

        // Clean up any student file requests and files on disk
        $reqQuery = $conn->prepare("SELECT supporting_file, approved_file FROM file_requests WHERE student_id = ?");
        if ($reqQuery) {
            $reqQuery->bind_param("i", $id);
            $reqQuery->execute();
            $reqRes = $reqQuery->get_result();
            while ($rRow = $reqRes->fetch_assoc()) {
                if (!empty($rRow['supporting_file'])) {
                    delete_record_file($rRow['supporting_file']);
                }
                if (!empty($rRow['approved_file'])) {
                    delete_record_file($rRow['approved_file']);
                }
            }
            $reqQuery->close();
            $conn->query("DELETE FROM file_requests WHERE student_id = " . intval($id));
        }

        // Prepare SQL using student primary key
        $deleteSql = "DELETE FROM students WHERE id = ?";
        if ($stmt = $conn->prepare($deleteSql)) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success'] = "Student record and all associated files deleted successfully.";
                } else {
                    $_SESSION['error'] = "Student record not found or already deleted.";
                }
            } else {
                $_SESSION['error'] = "Failed to delete student record: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Database query preparation failed: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Invalid student record ID.";
    }
}

header("Location: dashboard.php");
exit();
