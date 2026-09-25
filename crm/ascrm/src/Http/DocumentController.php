<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Request;
use DR\Core\Vault;
use DR\Service\Cases;
use DR\Service\Notify;

/** Chunked encrypted uploads and audited downloads for every role. */
final class DocumentController extends Controller
{
    public function start(array $p, ?array $u): void
    {
        $staff = Auth::isStaff($u);
        $clientId = $u['role'] === 'client' ? (int) $u['client_id'] : Request::int('client_id');
        $caseId = Request::int('case_id') ?: null;
        $case = null;
        if ($caseId) {
            $case = Access::loadCase($u, $caseId);
            if (!$case['client_id']) {
                throw new HttpError('Documents can be added once the lead is accepted.', 422);
            }
            $clientId = (int) $case['client_id'];
        }
        $client = Access::loadClient($u, $clientId);
        if (!$staff) {
            if (!$case) {
                throw new HttpError('Choose the case this document belongs to.', 422);
            }
            if ($case['status'] === 'closed') {
                throw new HttpError('This case is closed. Open a new request if you need to share more.', 422);
            }
            if ((int) $case['nda_required'] === 1 && !$case['nda_signed_at']) {
                throw new HttpError('Please sign the confidentiality agreement before sharing documents.', 403);
            }
        }
        $visibility = $staff ? Request::oneOf('visibility', Labels::VISIBILITY, 'internal') : 'shared';
        if ($visibility === 'confidential' && !in_array($u['role'], ['master', 'admin'], true) && (!$case || (int) $case['lead_user_id'] !== (int) $u['id'])) {
            throw new HttpError('Only the case lead or an admin can mark documents confidential.', 403);
        }
        $folderId = $staff ? (Request::int('folder_id') ?: null) : null;
        if ($folderId && !Db::value('SELECT id FROM folders WHERE id = ? AND client_id = ?', [$folderId, $clientId])) {
            $folderId = null;
        }
        $replaces = Request::int('replaces_id') ?: null;
        if ($replaces) {
            $prev = Db::one('SELECT * FROM documents WHERE id = ? AND client_id = ? AND deleted_at IS NULL', [$replaces, $clientId]);
            if (!$prev || !Access::documentVisible($u, $prev) || (!$staff && (int) $prev['uploaded_by'] !== (int) $u['id'])) {
                throw new HttpError('You cannot replace that document.', 403);
            }
            $visibility = $prev['visibility'];
            $folderId = $prev['folder_id'] ? (int) $prev['folder_id'] : null;
        }
        $messageId = Request::int('message_id') ?: null;
        if ($messageId && (!$case || !Db::value('SELECT id FROM messages WHERE id = ? AND case_id = ? AND sender_id = ?', [$messageId, (int) $case['id'], (int) $u['id']]))) {
            $messageId = null;
        }
        if (!\DR\Core\RateLimit::attempt('upload:' . $u['id'], 300, 3600)) {
            throw new HttpError('Upload limit reached for this hour. Please try again later.', 429);
        }
        $res = Vault::start($u, $client, $case ? (int) $case['id'] : null, $folderId, Request::str('name', 255), Request::int('size'), $visibility, $replaces, $messageId);
        App::json(['ok' => true] + $res);
    }

    public function chunk(array $p, ?array $u): void
    {
        $max = Vault::CHUNK_BYTES + 1024;
        $in = fopen('php://input', 'rb');
        $bytes = stream_get_contents($in, $max + 1);
        fclose($in);
        if ($bytes === false || strlen($bytes) > $max) {
            throw new HttpError('Chunk too large.', 413);
        }
        $received = Vault::chunk($u, (string) ($p['id'] ?? ''), Request::int('i', -1), $bytes);
        App::json(['ok' => true, 'received' => $received]);
    }

    public function finish(array $p, ?array $u): void
    {
        $doc = Vault::finish($u, (string) ($p['id'] ?? ''));
        $case = $doc['case_id'] ? Db::one('SELECT * FROM cases WHERE id = ?', [(int) $doc['case_id']]) : null;
        if (!Auth::isStaff($u)) {
            if ($case) {
                Cases::touch((int) $case['id']);
                Notify::caseTeam($case, 'client_upload', 'Client uploaded a document to ' . $case['ref'], (int) $u['id']);
            }
        } elseif ($doc['visibility'] === 'shared' && !$doc['message_id']) {
            Notify::clientUsers((int) $doc['client_id'], 'document_shared', 'A document has been shared with you' . ($case ? ' on ' . $case['ref'] : ''),
                $case ? '/client/cases/' . $case['id'] . '?tab=documents' : '/client/documents', (int) $u['id']);
        }
        App::json(['ok' => true, 'document' => [
            'id' => (int) $doc['id'], 'name' => (string) Crypto::fromClient((int) $doc['client_id'], $doc['name_enc']),
            'size' => human_size((int) $doc['size']), 'version' => (int) $doc['version'],
        ]]);
    }

    private function load(array $p, array $u): array
    {
        $doc = Db::one('SELECT * FROM documents WHERE id = ? AND deleted_at IS NULL', [$this->id($p)]);
        if (!$doc || !Access::documentVisible($u, $doc)) {
            if ($doc) {
                Audit::log('access_denied', 'document', (int) $doc['id'], [], $u);
            }
            throw new HttpError('Document not found.', 404);
        }
        return $doc;
    }

    public function download(array $p, ?array $u): void
    {
        $doc = $this->load($p, $u);
        Audit::log('document_downloaded', 'document', (int) $doc['id'], ['client_id' => (int) $doc['client_id'], 'case_id' => $doc['case_id'] ? (int) $doc['case_id'] : null, 'inline' => 0], $u);
        Vault::stream($doc, false);
    }

    public function inline(array $p, ?array $u): void
    {
        $doc = $this->load($p, $u);
        Audit::log('document_viewed', 'document', (int) $doc['id'], ['client_id' => (int) $doc['client_id'], 'case_id' => $doc['case_id'] ? (int) $doc['case_id'] : null, 'inline' => 1], $u);
        Vault::stream($doc, true);
    }

    public function update(array $p, ?array $u): void
    {
        $doc = $this->load($p, $u);
        $vis = Request::oneOf('visibility', Labels::VISIBILITY, $doc['visibility']);
        $case = $doc['case_id'] ? Db::one('SELECT * FROM cases WHERE id = ?', [(int) $doc['case_id']]) : null;
        $canConfidential = in_array($u['role'], ['master', 'admin'], true) || ($case && (int) $case['lead_user_id'] === (int) $u['id']);
        if (($vis === 'confidential' || $doc['visibility'] === 'confidential') && !$canConfidential) {
            throw new HttpError('Only the case lead or an admin can change confidential documents.', 403);
        }
        $folder = Request::int('folder_id') ?: null;
        if ($folder && !Db::value('SELECT id FROM folders WHERE id = ? AND client_id = ?', [$folder, (int) $doc['client_id']])) {
            $folder = null;
        }
        $root = (int) ($doc['root_id'] ?: $doc['id']);
        Db::run('UPDATE documents SET visibility = ?, folder_id = ? WHERE id = ? OR root_id = ?', [$vis, $folder, $root, $root]);
        Audit::log('document_updated', 'document', (int) $doc['id'], ['client_id' => (int) $doc['client_id'], 'case_id' => $doc['case_id'] ? (int) $doc['case_id'] : null, 'visibility' => $vis], $u);
        if ($vis === 'shared' && $doc['visibility'] !== 'shared') {
            Notify::clientUsers((int) $doc['client_id'], 'document_shared', 'A document has been shared with you' . ($case ? ' on ' . $case['ref'] : ''),
                $case ? '/client/cases/' . $case['id'] . '?tab=documents' : '/client/documents');
        }
        $this->flash('ok', 'Document updated.');
        App::back('/clients/' . $doc['client_id']);
    }

    /** Staff only: deletes one version and crypto-destroys its ciphertext. */
    public function delete(array $p, ?array $u): void
    {
        if (!Auth::isStaff($u)) {
            throw new HttpError('Documents you share are kept as part of your case record. Ask your case lead if something should be removed.', 403);
        }
        $doc = $this->load($p, $u);
        if (!Auth::atLeast($u, 'lead') && (int) $doc['uploaded_by'] !== (int) $u['id']) {
            throw new HttpError('Only a case lead or the uploader can delete this document.', 403);
        }
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [(int) $doc['client_id']]);
        $path = Vault::pathFor($client, $doc['storage_id']);
        if (is_file($path)) {
            $size = filesize($path) ?: 0;
            $fh = fopen($path, 'r+b');
            for ($o = 0; $fh && $o < $size; $o += 65536) {
                fwrite($fh, random_bytes((int) min(65536, $size - $o)));
            }
            if ($fh) {
                fclose($fh);
            }
            unlink($path);
        }
        Db::update('documents', ['deleted_at' => now(), 'is_current' => 0], 'id = :id', ['id' => (int) $doc['id']]);
        if ((int) $doc['is_current'] === 1) {
            $root = (int) ($doc['root_id'] ?: $doc['id']);
            $prev = Db::one('SELECT id FROM documents WHERE (id = ? OR root_id = ?) AND deleted_at IS NULL ORDER BY version DESC LIMIT 1', [$root, $root]);
            if ($prev) {
                Db::update('documents', ['is_current' => 1], 'id = :id', ['id' => (int) $prev['id']]);
            }
        }
        Audit::log('document_deleted', 'document', (int) $doc['id'], ['client_id' => (int) $doc['client_id'], 'case_id' => $doc['case_id'] ? (int) $doc['case_id'] : null], $u);
        $this->flash('ok', 'Document deleted and its encrypted file destroyed.');
        App::back('/clients/' . $doc['client_id']);
    }

    public function createFolder(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, Request::int('client_id'));
        $name = Request::str('name', 120);
        if ($name === '') {
            $this->fail('Enter a folder name.', '/clients/' . $client['id']);
        }
        $caseId = Request::int('case_id') ?: null;
        if ($caseId) {
            $case = Access::loadCase($u, $caseId);
            if ((int) $case['client_id'] !== (int) $client['id']) {
                throw new HttpError('Case and client do not match.', 422);
            }
        }
        Db::insert('folders', [
            'client_id' => (int) $client['id'], 'case_id' => $caseId, 'parent_id' => null,
            'name_enc' => Crypto::forClient((int) $client['id'], $name), 'created_by' => (int) $u['id'], 'created_at' => now(),
        ]);
        $this->flash('ok', 'Folder created.');
        App::back('/clients/' . $client['id']);
    }

    /** Documents for a client (optionally a case), decrypted names, current versions first. */
    public static function listFor(array $u, int $clientId, ?int $caseId = null): array
    {
        $sql = 'SELECT d.*, us.name AS uploader FROM documents d LEFT JOIN users us ON us.id = d.uploaded_by WHERE d.client_id = ? AND d.deleted_at IS NULL';
        $params = [$clientId];
        if ($caseId) {
            $sql .= ' AND d.case_id = ?';
            $params[] = $caseId;
        }
        $rows = Db::all($sql . ' ORDER BY d.created_at DESC, d.id DESC', $params);
        $out = [];
        foreach ($rows as $d) {
            if (!Access::documentVisible($u, $d)) {
                continue;
            }
            $d['name'] = (string) Crypto::fromClient($clientId, $d['name_enc']);
            $out[] = $d;
        }
        return $out;
    }

    public static function foldersFor(int $clientId): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM folders WHERE client_id = ? ORDER BY id', [$clientId]) as $f) {
            $out[(int) $f['id']] = (string) Crypto::fromClient($clientId, $f['name_enc']);
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }
}
