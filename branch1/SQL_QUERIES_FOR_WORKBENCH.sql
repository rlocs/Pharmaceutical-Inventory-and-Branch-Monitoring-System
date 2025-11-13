-- =====================================================
-- IMPROVED GET_CONVERSATIONS QUERY
-- Use this query to test in MySQL Workbench
-- Replace ? with actual user_id when testing
-- =====================================================

-- For testing, replace ? with a specific user ID (e.g., 1, 2, 3, etc.)
-- Example: SET @current_user_id = 1;

SET @current_user_id = 1; -- Change this to the user ID you want to test

SELECT DISTINCT
    c.ConversationID,
    c.LastMessageTimestamp,
    -- Other participant details (get the first other participant)
    (SELECT a.FirstName
     FROM ChatParticipants cp
     JOIN Accounts a ON cp.UserID = a.UserID
     WHERE cp.ConversationID = c.ConversationID
       AND cp.UserID != @current_user_id
     ORDER BY cp.UserID
     LIMIT 1) AS FirstName,
    (SELECT a.LastName
     FROM ChatParticipants cp
     JOIN Accounts a ON cp.UserID = a.UserID
     WHERE cp.ConversationID = c.ConversationID
       AND cp.UserID != @current_user_id
     ORDER BY cp.UserID
     LIMIT 1) AS LastName,
    (SELECT b.BranchName
     FROM ChatParticipants cp
     JOIN Accounts a ON cp.UserID = a.UserID
     JOIN Branches b ON a.BranchID = b.BranchID
     WHERE cp.ConversationID = c.ConversationID
       AND cp.UserID != @current_user_id
     ORDER BY cp.UserID
     LIMIT 1) AS BranchName,
    -- Last message
    (SELECT cm.MessageContent
     FROM ChatMessages cm
     WHERE cm.ConversationID = c.ConversationID
     ORDER BY cm.Timestamp DESC
     LIMIT 1) AS LastMessage,
    -- Unread message count
    (SELECT COUNT(*)
     FROM ChatMessages cm
     WHERE cm.ConversationID = c.ConversationID
       AND cm.Timestamp > COALESCE((
           SELECT cp.LastReadTimestamp
           FROM ChatParticipants cp
           WHERE cp.ConversationID = c.ConversationID
             AND cp.UserID = @current_user_id
           LIMIT 1
       ), '1970-01-01')) AS UnreadCount
FROM ChatConversations c
INNER JOIN ChatParticipants p ON c.ConversationID = p.ConversationID
WHERE p.UserID = @current_user_id
GROUP BY c.ConversationID
ORDER BY c.LastMessageTimestamp DESC;

-- =====================================================
-- ALTERNATIVE: Query to check for duplicate conversations
-- Use this to identify if there are actual duplicates in your database
-- =====================================================

-- Check for duplicate conversations for a specific user
SELECT 
    p.UserID,
    c.ConversationID,
    COUNT(*) as participant_count,
    GROUP_CONCAT(DISTINCT a.FirstName, ' ', a.LastName SEPARATOR ', ') as other_participants
FROM ChatParticipants p
JOIN ChatConversations c ON p.ConversationID = c.ConversationID
LEFT JOIN ChatParticipants cp2 ON c.ConversationID = cp2.ConversationID AND cp2.UserID != p.UserID
LEFT JOIN Accounts a ON cp2.UserID = a.UserID
WHERE p.UserID = @current_user_id
GROUP BY p.UserID, c.ConversationID
HAVING COUNT(DISTINCT cp2.UserID) > 0
ORDER BY c.ConversationID;

-- =====================================================
-- Query to find and remove duplicate conversation entries
-- (Use with caution - backup your database first!)
-- =====================================================

-- First, identify duplicate conversations (same two users, multiple conversation records)
SELECT 
    cp1.UserID as User1,
    cp2.UserID as User2,
    COUNT(DISTINCT cp1.ConversationID) as conversation_count,
    GROUP_CONCAT(DISTINCT cp1.ConversationID ORDER BY cp1.ConversationID SEPARATOR ', ') as conversation_ids
FROM ChatParticipants cp1
JOIN ChatParticipants cp2 ON cp1.ConversationID = cp2.ConversationID
WHERE cp1.UserID < cp2.UserID  -- Avoid duplicate pairs (A-B and B-A)
GROUP BY cp1.UserID, cp2.UserID
HAVING COUNT(DISTINCT cp1.ConversationID) > 1;

-- =====================================================
-- If duplicates are found, you can use this to keep only the oldest conversation
-- WARNING: This will delete duplicate conversations. BACKUP FIRST!
-- =====================================================

/*
-- Step 1: Identify which conversations to keep (keep the one with the oldest LastMessageTimestamp)
CREATE TEMPORARY TABLE conversations_to_keep AS
SELECT 
    cp1.UserID,
    cp2.UserID,
    MIN(c.ConversationID) as keep_conversation_id
FROM ChatParticipants cp1
JOIN ChatParticipants cp2 ON cp1.ConversationID = cp2.ConversationID
JOIN ChatConversations c ON cp1.ConversationID = c.ConversationID
WHERE cp1.UserID < cp2.UserID
GROUP BY cp1.UserID, cp2.UserID
HAVING COUNT(DISTINCT cp1.ConversationID) > 1;

-- Step 2: Delete duplicate conversations (keep only the one in conversations_to_keep)
-- UNCOMMENT ONLY IF YOU'RE SURE AND HAVE BACKED UP YOUR DATABASE!
-- DELETE FROM ChatMessages WHERE ConversationID NOT IN (SELECT keep_conversation_id FROM conversations_to_keep);
-- DELETE FROM ChatParticipants WHERE ConversationID NOT IN (SELECT keep_conversation_id FROM conversations_to_keep);
-- DELETE FROM ChatConversations WHERE ConversationID NOT IN (SELECT keep_conversation_id FROM conversations_to_keep);
*/

