import React, { useEffect, useState } from 'react';
import { FlatList, StyleSheet, Text, TextInput, View } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import DropdownField from '../../components/DropdownField';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

function MessageSeparator() {
  return <View style={styles.messageSeparator} />;
}

export default function ChatScreen({ navigation }) {
  const { currentRole, currentUser, rides, messages, refreshDashboard, sendMessage } = useAppContext();
  const [draft, setDraft] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const ride = rides[0];

  const targetOptions = [
    ride?.parentUserId && currentRole !== 'parent' ? { label: `Parent - ${ride.parentName}`, value: ride.parentUserId } : null,
    ride?.driverUserId && currentRole !== 'driver' ? { label: `Driver - ${ride.driverName}`, value: ride.driverUserId } : null,
    ride?.studentUserId && currentRole !== 'student' ? { label: `Student - ${ride.studentName}`, value: ride.studentUserId } : null,
    ...messages.map((message) => {
      const otherUserId = message.senderUserId === currentUser?.id ? message.receiverUserId : message.senderUserId;
      const otherName = message.senderUserId === currentUser?.id ? message.receiverName : message.senderName;
      const otherRole = message.senderUserId === currentUser?.id ? message.receiverRole : message.senderRole;

      return otherUserId ? { label: `${otherRole} - ${otherName}`, value: otherUserId } : null;
    }),
  ]
    .filter(Boolean)
    .filter((option, index, options) => options.findIndex((item) => String(item.value) === String(option.value)) === index);
  const [receiverUserId, setReceiverUserId] = useState(targetOptions[0]?.value || '');

  useEffect(() => {
    if (!receiverUserId && targetOptions[0]?.value) {
      setReceiverUserId(targetOptions[0].value);
    }
  }, [receiverUserId, targetOptions]);

  const visibleMessages = messages.filter(
    (message) =>
      !receiverUserId ||
      String(message.senderUserId) === String(receiverUserId) ||
      String(message.receiverUserId) === String(receiverUserId)
  );
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen scroll={false} style={styles.screen} bottomBar={<AppNavBar navigation={navigation} active={currentRole === 'student' ? 'support' : 'bookings'} />}>
      <HeaderBlock
        eyebrow="Messaging"
        title="Trip coordination chat."
        subtitle="Parents, drivers, and students can coordinate pickup timing and urgent updates here."
      />

      <SectionCard title="Conversation" icon="comments">
        {targetOptions.length ? (
          <DropdownField label="Message recipient" value={receiverUserId} options={targetOptions} placeholder="Choose a contact" onChange={setReceiverUserId} />
        ) : null}
        <FlatList
          data={visibleMessages}
          refreshing={refreshing}
          onRefresh={handleRefresh}
          keyExtractor={(item) => String(item.id)}
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
          icon="paper-plane"
          label="Send"
          onPress={async () => {
            if (!draft.trim()) {
              return;
            }

            await sendMessage({ text: draft, receiverUserId, rideId: ride?.id });
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
