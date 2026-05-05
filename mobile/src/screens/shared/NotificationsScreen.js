import React from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function NotificationsScreen({ navigation }) {
  const { currentRole, notifications } = useAppContext();
  const visibleNotifications = notifications.filter((item) => item.role === currentRole);

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="alerts" />}>
      <HeaderBlock
        eyebrow="Alerts"
        title="Recent system notifications."
        subtitle="These are role-filtered in the prototype and should later come from the PHP notifications table."
      />

      {visibleNotifications.map((item) => (
        <SectionCard key={item.id} title={item.title} subtitle={item.body}>
          <Pill label={item.time} />
        </SectionCard>
      ))}

      {visibleNotifications.length === 0 ? (
        <SectionCard title="No notifications">
          <Text>Your account has no alerts yet.</Text>
        </SectionCard>
      ) : null}
    </Screen>
  );
}
