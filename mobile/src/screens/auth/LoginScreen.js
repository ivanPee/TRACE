import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function LoginScreen({ navigation }) {
  const { loginAsRole } = useAppContext();

  const handleDemoLogin = (role) => {
    loginAsRole(role);

    if (role === 'parent') {
      navigation.replace('ParentDashboard');
      return;
    }

    if (role === 'driver') {
      navigation.replace('DriverDashboard');
      return;
    }

    navigation.replace('StudentDashboard');
  };

  return (
    <Screen>
      <HeaderBlock
        eyebrow="Demo Access"
        title="Sign in quickly using the sample user roles."
        subtitle="The UI is wired to local mock state for now, so you can test the flows while the PHP API is still being connected."
      />

      <SectionCard title="Parent account" subtitle="Manage students, book rides, and track vehicles.">
        <AppButton label="Continue as Parent" onPress={() => handleDemoLogin('parent')} />
      </SectionCard>

      <SectionCard title="Driver account" subtitle="Receive bookings, update ride status, and share live location.">
        <AppButton label="Continue as Driver" variant="secondary" onPress={() => handleDemoLogin('driver')} />
      </SectionCard>

      <SectionCard title="Student account" subtitle="View trip status, ETA, and emergency reminders.">
        <AppButton label="Continue as Student" variant="ghost" onPress={() => handleDemoLogin('student')} />
      </SectionCard>

      <View style={styles.footer}>
        <Text style={styles.footerText}>Need a new account instead?</Text>
        <AppButton label="Go to Registration" variant="ghost" onPress={() => navigation.navigate('RegisterRole')} />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  footer: {
    marginTop: 6,
  },
  footerText: {
    fontSize: 14,
    marginBottom: 10,
    color: '#5c677d',
  },
});
