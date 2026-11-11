<?php

declare(strict_types=1);

use App\Controllers\ChatController;
use App\Controllers\KnowledgeController;
use App\Controllers\UnansweredController;

/** @var \App\Core\Router $router */

// گفتگو
$router->get('/', [ChatController::class, 'index']);
$router->post('/api/chat/ask', [ChatController::class, 'ask']);
$router->get('/api/chat/{conv}', [ChatController::class, 'history']);

// دانشنامه (پرسش و پاسخ)
$router->get('/knowledge', [KnowledgeController::class, 'index']);
$router->post('/knowledge', [KnowledgeController::class, 'store']);
$router->get('/knowledge/{id}/edit', [KnowledgeController::class, 'edit']);
$router->post('/knowledge/{id}/update', [KnowledgeController::class, 'update']);
$router->post('/knowledge/{id}/delete', [KnowledgeController::class, 'delete']);

// سوال‌های بی‌پاسخ
$router->get('/unanswered', [UnansweredController::class, 'index']);
$router->post('/unanswered/{id}/dismiss', [UnansweredController::class, 'dismiss']);
