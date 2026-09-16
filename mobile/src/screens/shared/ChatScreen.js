import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, FlatList, Image, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import DropdownField from '../../components/DropdownField';
import Screen from '../../components/Screen';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

function MessageSeparator() {
  return <View style={styles.messageSeparator} />;
}

function initialsFor(name) {
  return String(name || 'TRACE User')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase() || 'TU';
}

function imageUrlForMessage(message) {
  const explicitUrl = message.imageUrl ?? message.image_url ?? null;
  const text = String(message.text ?? message.message_text ?? '');
  const candidate = explicitUrl || text;

  return /\.(jpe?g|png|webp|gif)(\?.*)?$/i.test(candidate) ? candidate : null;
}

export default function ChatScreen() {
  const { currentRole, currentUser, rides, messages, refreshDashboard, sendMessage } = useAppContext();
  const [draft, setDraft] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const [sending, setSending] = useState(false);
  const listRef = useRef(null);
  const mountedRef = useRef(true);
  const ride = Array.isArray(rides) ? rides[0] : null;
  const safeMessages = useMemo(() => (Array.isArray(messages) ? messages : []), [messages]);

  const normalizedMessages = useMemo(
    () => safeMessages.map((message, index) => {
      const senderUserId = message.senderUserId ?? message.sender_user_id;
      const receiverUserId = message.receiverUserId ?? message.receiver_user_id;

      const imageUrl = imageUrlForMessage(message);

      return {
        id: message.id ?? `message-${index}`,
        senderUserId,
        receiverUserId,
        senderRole: message.senderRole ?? message.sender_role,
        receiverRole: message.receiverRole ?? message.receiver_role,
        senderName: message.senderName ?? message.sender_name ?? 'TRACE User',
        receiverName: message.receiverName ?? message.receiver_name ?? 'TRACE User',
        type: imageUrl ? 'image' : (message.type ?? message.messageType ?? message.message_type ?? 'text'),
        text: imageUrl ? String(message.text ?? 'Drop-off photo') : String(message.text ?? message.message_text ?? ''),
        imageUrl,
        time: String(message.time ?? message.created_at ?? ''),
      };
    }),
    [safeMessages]
  );

  const targetOptions = useMemo(
    () => [
      ride?.parentUserId && currentRole !== 'parent' ? { label: `Parent - ${ride.parentName || 'Parent'}`, value: ride.parentUserId } : null,
      ride?.driverUserId && currentRole !== 'driver' ? { label: `Driver - ${ride.driverName || 'Driver'}`, value: ride.driverUserId } : null,
      ride?.studentUserId && currentRole !== 'student' ? { label: `Child - ${ride.studentName || 'Child'}`, value: ride.studentUserId } : null,
      ...normalizedMessages.map((message) => {
        const isSender = String(message.senderUserId) === String(currentUser?.id);
        const otherUserId = isSender ? message.receiverUserId : message.senderUserId;
        const otherName = isSender ? message.receiverName : message.senderName;
        const otherRole = isSender ? message.receiverRole : message.senderRole;

        return otherUserId ? { label: `${otherRole || 'User'} - ${otherName || 'TRACE User'}`, value: otherUserId } : null;
      }),
    ]
      .filter(Boolean)
      .filter((option, index, options) => options.findIndex((item) => String(item.value) === String(option.value)) === index),
    [currentRole, currentUser?.id, normalizedMessages, ride]
  );
  const [receiverUserId, setReceiverUserId] = useState(targetOptions[0]?.value || '');

  useEffect(() => {
    const hasSelectedContact = targetOptions.some((option) => String(option.value) === String(receiverUserId));

    if ((!receiverUserId || !hasSelectedContact) && targetOptions[0]?.value) {
      setReceiverUserId(targetOptions[0].value);
    }
  }, [receiverUserId, targetOptions]);

  const visibleMessages = useMemo(
    () =>
      normalizedMessages.filter(
        (message) =>
          !receiverUserId ||
          String(message.senderUserId) === String(receiverUserId) ||
          String(message.receiverUserId) === String(receiverUserId)
      ),
    [normalizedMessages, receiverUserId]
  );
  const selectedContact = targetOptions.find((option) => String(option.value) === String(receiverUserId));
  const conversationTitle = selectedContact?.label?.replace(/^[^-]+ - /, '') || (currentRole === 'student' ? 'Parent' : currentRole === 'driver' ? 'Parent' : 'Driver');
  const conversationRole = selectedContact?.label?.includes(' - ') ? selectedContact.label.split(' - ')[0] : 'Messages';

  const scrollToLatestMessage = useCallback((animated = true) => {
    if (!mountedRef.current || !visibleMessages.length) {
      return;
    }

    requestAnimationFrame(() => {
      if (mountedRef.current) {
        listRef.current?.scrollToEnd({ animated });
      }
    });
  }, [visibleMessages.length]);

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    scrollToLatestMessage(true);
  }, [scrollToLatestMessage]);

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen scroll={false} style={styles.screen}>
      <KeyboardAvoidingView style={styles.keyboardAvoider} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={styles.chatHeader}>
          <View style={styles.headerAvatar}>
            <Text style={styles.headerAvatarText}>{initialsFor(conversationTitle)}</Text>
          </View>
          <View style={styles.headerText}>
            <Text style={styles.headerTitle} numberOfLines={1}>{conversationTitle}</Text>
            <Text style={styles.headerSubtitle} numberOfLines={1}>{ride ? `${conversationRole} / Trip #${ride.id}` : conversationRole}</Text>
          </View>
        </View>

        <View style={styles.recipientArea}>
          {targetOptions.length ? (
            <DropdownField label="Message recipient" value={receiverUserId} options={targetOptions} placeholder="Choose a contact" onChange={setReceiverUserId} />
          ) : (
            <View style={styles.noRecipient}>
              <FontAwesome5 name="info-circle" size={13} color={colors.slate} />
              <Text style={styles.noRecipientText}>Messages will use your latest available trip contact.</Text>
            </View>
          )}
        </View>

        <FlatList
          ref={listRef}
          data={visibleMessages}
          style={styles.messagesList}
          contentContainerStyle={styles.messagesContent}
          refreshing={refreshing}
          onRefresh={handleRefresh}
          keyboardShouldPersistTaps="handled"
          nestedScrollEnabled
          showsVerticalScrollIndicator={false}
          onContentSizeChange={() => scrollToLatestMessage(true)}
          onLayout={() => scrollToLatestMessage(false)}
          keyExtractor={(item, index) => String(item.id ?? index)}
          ListEmptyComponent={
            <View style={styles.emptyState}>
              <View style={styles.emptyIcon}>
                <FontAwesome5 name="comments" size={22} solid color={colors.accent} />
              </View>
              <Text style={styles.emptyTitle}>No messages yet</Text>
              <Text style={styles.emptyText}>Start the conversation for pickup updates, ETA changes, or trip coordination.</Text>
            </View>
          }
          renderItem={({ item }) => {
            const isOwn = String(item.senderUserId) === String(currentUser?.id) || item.senderRole === currentRole;

            return (
              <View style={[styles.messageRow, isOwn ? styles.ownRow : styles.otherRow]}>
                {!isOwn ? (
                  <View style={styles.avatar}>
                    <Text style={styles.avatarText}>{initialsFor(item.senderName)}</Text>
                  </View>
                ) : null}
                <View style={[styles.bubble, isOwn ? styles.ownBubble : styles.otherBubble]}>
                  {!isOwn ? <Text style={styles.sender} numberOfLines={1}>{item.senderName}</Text> : null}
                  {item.imageUrl ? (
                    <View style={styles.imageMessage}>
                      <Image source={{ uri: item.imageUrl }} style={styles.messageImage} resizeMode="cover" />
                      <Text style={[styles.imageCaption, isOwn ? styles.ownBody : null]}>{item.text || 'Drop-off photo'}</Text>
                    </View>
                  ) : (
                    <Text style={[styles.body, isOwn ? styles.ownBody : null]}>{item.text}</Text>
                  )}
                  <Text style={[styles.time, isOwn ? styles.ownTime : null]}>{item.time}</Text>
                </View>
              </View>
            );
          }}
          ItemSeparatorComponent={MessageSeparator}
        />

        <View style={styles.composerShell}>
          <View style={styles.composer}>
            <FontAwesome5 name="comment-dots" size={16} solid color={colors.slate} />
          <TextInput
            value={draft}
            onChangeText={setDraft}
              placeholder="Message"
            placeholderTextColor={colors.placeholder}
            style={styles.input}
            multiline
            textAlignVertical="top"
              returnKeyType="default"
          />
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Send message"
            disabled={sending || !draft.trim()}
              style={({ pressed }) => [styles.sendButton, (!draft.trim() || sending) && styles.sendButtonDisabled, pressed && draft.trim() && !sending ? styles.sendButtonPressed : null]}
            onPress={async () => {
              const text = draft.trim();

              if (!text) {
                return;
              }

              try {
                setSending(true);
                await sendMessage({ text, ...(receiverUserId ? { receiverUserId } : {}), ...(ride?.id ? { rideId: ride.id } : {}) });
                setDraft('');
              } catch (error) {
                Alert.alert('Message not sent', error.message || 'Please try again.');
              } finally {
                setSending(false);
              }
            }}
            >
              <FontAwesome5 name={sending ? 'spinner' : 'paper-plane'} size={15} solid color={colors.white} />
            </Pressable>
          </View>
        </View>
      </KeyboardAvoidingView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    paddingHorizontal: 14,
    paddingTop: 12,
    paddingBottom: 10,
  },
  keyboardAvoider: {
    flex: 1,
  },
  chatHeader: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 18,
    borderWidth: 1,
    flexDirection: 'row',
    marginBottom: 10,
    padding: 12,
    shadowColor: colors.shadow,
    shadowOpacity: 0.08,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
  headerAvatar: {
    alignItems: 'center',
    backgroundColor: colors.accent,
    borderRadius: 22,
    height: 44,
    justifyContent: 'center',
    marginRight: 12,
    width: 44,
  },
  headerAvatarText: {
    color: colors.deep,
    fontSize: 15,
    fontWeight: '900',
  },
  headerText: {
    flex: 1,
    minWidth: 0,
  },
  headerTitle: {
    color: colors.ink,
    fontSize: 18,
    fontWeight: '900',
  },
  headerSubtitle: {
    color: colors.slate,
    fontSize: 12,
    fontWeight: '700',
    marginTop: 2,
    textTransform: 'capitalize',
  },
  recipientArea: {
    marginBottom: 4,
  },
  noRecipient: {
    alignItems: 'center',
    backgroundColor: colors.sky,
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 8,
    marginBottom: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  noRecipientText: {
    color: colors.slate,
    flex: 1,
    fontSize: 12,
    lineHeight: 17,
  },
  messageRow: {
    alignItems: 'flex-end',
    flexDirection: 'row',
    maxWidth: '100%',
  },
  ownRow: {
    justifyContent: 'flex-end',
  },
  otherRow: {
    justifyContent: 'flex-start',
  },
  avatar: {
    alignItems: 'center',
    backgroundColor: colors.sand,
    borderRadius: 14,
    height: 28,
    justifyContent: 'center',
    marginRight: 8,
    width: 28,
  },
  avatarText: {
    color: colors.ink,
    fontSize: 10,
    fontWeight: '900',
  },
  bubble: {
    borderRadius: 20,
    maxWidth: '78%',
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  ownBubble: {
    backgroundColor: colors.ink,
    borderBottomRightRadius: 6,
  },
  otherBubble: {
    backgroundColor: colors.white,
    borderBottomLeftRadius: 6,
    borderColor: colors.line,
    borderWidth: 1,
  },
  sender: {
    color: colors.ink,
    fontWeight: '800',
    marginBottom: 4,
  },
  body: {
    color: colors.ink,
    lineHeight: 20,
    fontSize: 15,
  },
  ownBody: {
    color: colors.white,
  },
  imageMessage: {
    gap: 6,
  },
  messageImage: {
    backgroundColor: colors.line,
    borderRadius: 14,
    height: 190,
    width: 230,
  },
  imageCaption: {
    color: colors.ink,
    fontSize: 12,
    fontWeight: '800',
  },
  time: {
    marginTop: 6,
    color: colors.slate,
    fontSize: 11,
  },
  ownTime: {
    color: 'rgba(255,255,255,0.72)',
  },
  messagesList: {
    flex: 1,
    minHeight: 0,
  },
  messagesContent: {
    flexGrow: 1,
    justifyContent: 'flex-end',
    paddingBottom: 12,
    paddingTop: 8,
  },
  messageSeparator: {
    height: 8,
  },
  emptyState: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 28,
    paddingVertical: 36,
  },
  emptyIcon: {
    alignItems: 'center',
    backgroundColor: colors.accentSoft,
    borderRadius: 24,
    height: 48,
    justifyContent: 'center',
    marginBottom: 12,
    width: 48,
  },
  emptyTitle: {
    color: colors.ink,
    fontSize: 17,
    fontWeight: '900',
    marginBottom: 4,
  },
  emptyText: {
    color: colors.slate,
    lineHeight: 20,
    textAlign: 'center',
  },
  composerShell: {
    backgroundColor: colors.paper,
    flexShrink: 0,
    paddingTop: 6,
  },
  composer: {
    alignItems: 'flex-end',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 24,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 10,
    minHeight: 50,
    paddingHorizontal: 14,
    paddingVertical: 8,
    shadowColor: colors.shadow,
    shadowOpacity: 0.1,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
  input: {
    color: colors.ink,
    flex: 1,
    fontSize: 15,
    maxHeight: 110,
    minHeight: 34,
    paddingHorizontal: 0,
    paddingVertical: 7,
  },
  sendButton: {
    alignItems: 'center',
    backgroundColor: colors.ink,
    borderRadius: 18,
    height: 36,
    justifyContent: 'center',
    width: 36,
  },
  sendButtonDisabled: {
    backgroundColor: colors.line,
  },
  sendButtonPressed: {
    opacity: 0.82,
  },
});
