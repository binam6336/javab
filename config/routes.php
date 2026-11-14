<?php

declare(strict_types=1);

use App\Controllers\AnalysisController;
use App\Controllers\ChatController;
use App\Controllers\FeedbackController;
use App\Controllers\KnowledgeController;
use App\Controllers\RuleController;
use App\Controllers\UnansweredController;

/** @var \App\Core\Router $router */

// گفتگو
$router->get('/', [ChatController::class, 'index']);
$router->post('/api/chat/ask', [ChatController::class, 'ask']);
$router->get('/api/chat/{conv}', [ChatController::class, 'history']);
$router->post('/api/chat/message/{id}/rate', [ChatController::class, 'rate']);
$router->post('/api/chat/message/{id}/correct', [ChatController::class, 'correct']);

// دانشنامه (پرسش و پاسخ)
$router->get('/knowledge', [KnowledgeController::class, 'index']);
$router->post('/knowledge', [KnowledgeController::class, 'store']);
$router->get('/knowledge/{id}/edit', [KnowledgeController::class, 'edit']);
$router->post('/knowledge/{id}/update', [KnowledgeController::class, 'update']);
$router->post('/knowledge/{id}/delete', [KnowledgeController::class, 'delete']);

// قوانین ثابت (JSON، پاپ‌آپ دانشنامه)
$router->get('/api/rules', [RuleController::class, 'index']);
$router->post('/api/rules', [RuleController::class, 'store']);
$router->post('/api/rules/{id}/update', [RuleController::class, 'update']);
$router->post('/api/rules/{id}/delete', [RuleController::class, 'delete']);

// تحلیل دانشنامه‌ها
$router->get('/analysis', [AnalysisController::class, 'index']);
$router->post('/api/analysis/start', [AnalysisController::class, 'start']);
$router->post('/api/analysis/step', [AnalysisController::class, 'step']);
$router->post('/analysis/{id}/except', [AnalysisController::class, 'except']);
$router->post('/analysis/{id}/unexcept', [AnalysisController::class, 'unexcept']);

// بازخورد و اصلاح‌ها
$router->get('/feedback', [FeedbackController::class, 'index']);
$router->post('/feedback/{id}/toggle', [FeedbackController::class, 'toggleFeedback']);
$router->post('/feedback/{id}/delete', [FeedbackController::class, 'deleteFeedback']);
$router->post('/corrections/{id}/update', [FeedbackController::class, 'updateCorrection']);
$router->post('/corrections/{id}/toggle', [FeedbackController::class, 'toggleCorrection']);
$router->post('/corrections/{id}/delete', [FeedbackController::class, 'deleteCorrection']);

// سوال‌های بی‌پاسخ
$router->get('/unanswered', [UnansweredController::class, 'index']);
$router->post('/unanswered/{id}/dismiss', [UnansweredController::class, 'dismiss']);
