import React, { useState } from 'react';
import { FlatList, StyleSheet, Text, TextInput, View } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

function MessageSeparator() {
  return <View style={styles.messageSeparator} />;
}

export default function ChatScreen({ navigation }) {
  const { currentRole, messages, sendMessage } = useAppContext();
  const [draft, setDraft] = useState('');

  const visibleMessages = messages.filter(
    (message) =>
      message.senderRole === currentRole ||
      message.receiverRole === currentRole ||
      (currentRole === 'student' && message.receiverRole === 'parent')
  );

  return (
    <Screen scroll={false} style={styles.screen} bottomBar={<AppNavBar navigation={navigation} active={currentRole === 'student' ? 'support' : 'bookings'} />}>
      <HeaderBlock
        eyebrow="Messaging"
        title="Trip coordination chat."
        subtitle="Parents, drivers, and students can coordinate pickup timing and urgent updates here."
      />

      <SectionCard title="Conversation">
        <FlatList
          data={visibleMessages}
          keyExtractor={(item) => item.id}
          renderItem={({ item }) => (
            <View style={[styles.message, item.senderRole === currentRole ? styles.own : styles.other]}>
              <Text style={styles.sender}>{item.senderName}</Text>
              <Text style={styles.body}>{item.text}</Text>
              <Text style={styles.time}>{item.time}</Text>
            </View>
          )}
          ItemSeparatorComponent={MessageSeparator}
        />
      </SectionCard>

      <View style={styles.composer}>
        <TextInput
          value={draft}
          onChangeText={setDraft}
          placeholder="Type a message"
          placeholderTextColor="#98a2b3"
          style={styles.input}
        />
        <AppButton
          label="Send"
          onPress={() => {
            sendMessage(draft);
            setDraft('');
          }}
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  screen: {
    flexGrow: 1,
  },
  message: {
    padding: 14,
    borderRadius: 18,
  },
  own: {
    backgroundColor: colors.accentSoft,
  },
  other: {
    backgroundColor: colors.sky,
  },
  sender: {
    color: colors.ink,
    fontWeight: '800',
    marginBottom: 4,
  },
  body: {
    color: colors.ink,
    lineHeight: 20,
  },
  time: {
    marginTop: 6,
    color: colors.slate,
    fontSize: 12,
  },
  composer: {
    marginTop: 8,
  },
  messageSeparator: {
    height: 10,
  },
  input: {
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 14,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: 12,
  },
});
