<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller
{
    private function api()
    {
        return $this->call->library('api');
    }

    private function db()
    {
        return $this->call->database();
    }

    private function body()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        return is_array($body) ? $body : [];
    }

    public function register()
    {
        $api = $this->api();
        $body = $this->body();
        $username = trim((string) ($body['username'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if (strlen($username) < 3 || strlen($username) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $api->respond_error('Provide a username (3-100 characters), valid email, and password of at least 8 characters.', 422);
        }

        $db = $this->db();
        $existing = $db->raw('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1', [$username, $email])->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $api->respond_error('That username or email is already registered.', 409);
        }

        $db->raw(
            'INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), 'user']
        );
        $userId = (int) $db->last_id();
        $tokens = $api->issue_tokens([
            'id' => $userId,
            'role' => 'user',
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $api->respond([
            'message' => 'Account created.',
            'user' => ['id' => $userId, 'username' => $username, 'email' => $email],
            'tokens' => $tokens,
        ], 201);
    }

    public function login()
    {
        $api = $this->api();
        $body = $this->body();
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        $user = $this->db()->raw(
            'SELECT id, username, email, password, role, is_active FROM users WHERE email = ? LIMIT 1',
            [$email]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) $user['is_active'] || !password_verify($password, $user['password'])) {
            $api->respond_error('Invalid email or password.', 401);
        }

        $tokens = $api->issue_tokens([
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $api->respond([
            'message' => 'Signed in.',
            'user' => ['id' => (int) $user['id'], 'username' => $user['username'], 'email' => $user['email']],
            'tokens' => $tokens,
        ]);
    }

    public function refresh()
    {
        $api = $this->api();
        $this->db();
        $api->refresh_access_token((string) ($this->body()['refresh_token'] ?? ''));
    }

    public function me()
    {
        $api = $this->api();
        $claims = $api->require_jwt();
        $user = $this->db()->raw(
            'SELECT id, username, email FROM users WHERE id = ? AND is_active = 1 LIMIT 1',
            [(int) $claims['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $api->respond_error('User not found.', 404);
        }

        $api->respond(['user' => $user]);
    }

    public function logout()
    {
        $api = $this->api();
        $api->require_jwt();
        $refreshToken = (string) ($this->body()['refresh_token'] ?? '');
        if ($refreshToken !== '') {
            $this->db();
            $api->revoke_refresh_token($refreshToken);
        }

        $api->respond(['message' => 'Signed out.']);
    }
}