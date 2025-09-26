<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Get user's conversations
     */
    public function conversations(Request $request)
    {
        $user = $request->user();
        $query = $user->conversations()
            ->with(['item', 'buyer', 'seller', 'latestMessage'])
            ->orderBy('last_message_at', 'desc');

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        $conversations = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'unread_messages_count' => $user->unread_messages_count
            ]
        ]);
    }

    /**
     * Get single conversation
     */
    public function show(Conversation $conversation)
    {
        // Check if user is participant
        if (!$conversation->hasParticipant(request()->user()->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to view this conversation'
            ], 403);
        }

        $conversation->load(['item', 'buyer', 'seller', 'participants']);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'is_active' => $conversation->is_active,
                'last_message_at' => $conversation->last_message_at,
                'messages_count' => $conversation->messages_count,
                'unread_messages_count' => $conversation->unread_messages_count,
                'item' => [
                    'id' => $conversation->item->id,
                    'title' => $conversation->item->title,
                    'price' => $conversation->item->price,
                    'formatted_price' => $conversation->item->formatted_price,
                    'main_image' => $conversation->item->main_image,
                    'status' => $conversation->item->status
                ],
                'buyer' => [
                    'id' => $conversation->buyer->id,
                    'name' => $conversation->buyer->name,
                    'avatar' => $conversation->buyer->avatar,
                    'is_verified' => $conversation->buyer->is_verified
                ],
                'seller' => [
                    'id' => $conversation->seller->id,
                    'name' => $conversation->seller->name,
                    'avatar' => $conversation->seller->avatar,
                    'is_verified' => $conversation->seller->is_verified
                ],
                'participants' => $conversation->participants->map(function($participant) {
                    return [
                        'id' => $participant->id,
                        'name' => $participant->name,
                        'avatar' => $participant->avatar,
                        'joined_at' => $participant->pivot->joined_at
                    ];
                }),
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at
            ]
        ]);
    }

    /**
     * Create new conversation
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
            'type' => 'sometimes|string|in:text,image,file'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $item = Item::findOrFail($request->item_id);
        $user = $request->user();

        // Check if user is not trying to message themselves
        if ($item->seller_id === $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'You cannot message yourself'
            ], 400);
        }

        // Check if conversation already exists
        $existingConversation = Conversation::where('item_id', $item->id)
            ->where('buyer_id', $user->id)
            ->where('seller_id', $item->seller_id)
            ->first();

        if ($existingConversation) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation already exists for this item'
            ], 400);
        }

        // Create conversation
        $conversation = Conversation::create([
            'item_id' => $item->id,
            'buyer_id' => $user->id,
            'seller_id' => $item->seller_id,
            'subject' => $request->subject ?? "Inquiry about {$item->title}",
            'is_active' => true
        ]);

        // Add participants
        $conversation->addParticipant($user->id);
        $conversation->addParticipant($item->seller_id);

        // Create first message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'recipient_id' => $item->seller_id,
            'content' => $request->message,
            'type' => $request->get('type', 'text')
        ]);

        // Update conversation last message
        $conversation->updateLastMessage();

        $conversation->load(['item', 'buyer', 'seller']);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'message' => $message,
            'message' => 'Conversation created successfully'
        ], 201);
    }

    /**
     * Send message to conversation
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
            'type' => 'sometimes|string|in:text,image,file',
            'attachments' => 'sometimes|array|max:5',
            'attachments.*' => 'file|max:10240'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Check if user is participant
        if (!$conversation->hasParticipant($request->user()->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to send message to this conversation'
            ], 403);
        }

        // Check if conversation is active
        if (!$conversation->isActive()) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation is not active'
            ], 400);
        }

        // Determine recipient
        $recipientId = $conversation->buyer_id === $request->user()->id 
            ? $conversation->seller_id 
            : $conversation->buyer_id;

        // Handle attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $path = $attachment->store('messages', 'public');
                $attachments[] = [
                    'name' => $attachment->getClientOriginalName(),
                    'path' => $path,
                    'size' => $attachment->getSize(),
                    'type' => $attachment->getMimeType()
                ];
            }
        }

        // Create message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'recipient_id' => $recipientId,
            'content' => $request->content,
            'type' => $request->get('type', 'text'),
            'attachments' => $attachments
        ]);

        // Update conversation last message
        $conversation->updateLastMessage();

        $message->load(['sender', 'recipient']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'message' => 'Message sent successfully'
        ], 201);
    }

    /**
     * Get conversation messages
     */
    public function messages(Conversation $conversation, Request $request)
    {
        // Check if user is participant
        if (!$conversation->hasParticipant($request->user()->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to view messages in this conversation'
            ], 403);
        }

        $query = $conversation->messages()
            ->with(['sender', 'recipient'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('unread_only')) {
            if ($request->boolean('unread_only')) {
                $query->where('recipient_id', $request->user()->id)
                      ->where('is_read', false);
            }
        }

        $messages = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'conversation' => [
                'id' => $conversation->id,
                'subject' => $conversation->subject,
                'is_active' => $conversation->is_active
            ]
        ]);
    }

    /**
     * Mark conversation as read
     */
    public function markAsRead(Conversation $conversation)
    {
        // Check if user is participant
        if (!$conversation->hasParticipant(request()->user()->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to mark this conversation as read'
            ], 403);
        }

        $conversation->markAsReadForUser(request()->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read'
        ]);
    }

    /**
     * Handle email webhook
     */
    public function emailWebhook(Request $request)
    {
        // Handle email webhook logic here
        // This would typically process incoming emails and create messages
        
        return response()->json([
            'success' => true,
            'message' => 'Email webhook processed'
        ]);
    }
}
