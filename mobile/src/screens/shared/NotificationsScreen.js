import React, { useState } from 'react';
import { Text } from 'react-native';
import AppNavBar from '../../components/AppNavBar';
import HeaderBlock from '../../components/HeaderBlock';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function NotificationsScreen({ navigation }) {
  const { currentRole, notifications, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const visibleNotifications = notifications.filter((item) => item.role === currentRole);
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Alerts"
        title="Recent system notifications."
        subtitle="Alerts come from trip status changes, assignment updates, and operational notices."
      />

      {visibleNotifications.map((item) => (
        <SectionCard key={item.id} title={item.title} subtitle={item.body} icon="bell">
          <Pill label={item.time} />
        </SectionCard>
      ))}

      {visibleNotifications.length === 0 ? (
        <SectionCard title="No notifications" icon="bell-slash">
          <Text>Your account has no alerts yet.</Text>
        </SectionCard>
      ) : null}
    </Screen>
  );
}
