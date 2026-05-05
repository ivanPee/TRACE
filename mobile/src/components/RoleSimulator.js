import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import AppButton from './AppButton';
import { colors } from '../theme/colors';

const roleRoutes = {
  parent: 'ParentDashboard',
  driver: 'DriverDashboard',
  student: 'StudentDashboard',
};

export default function RoleSimulator({ currentRole, loginAsRole, navigation }) {
  const switchRole = (role) => {
    loginAsRole(role);
    navigation.replace(roleRoutes[role]);
  };

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>Static simulator</Text>
      <View style={styles.row}>
        {Object.keys(roleRoutes).map((role) => (
          <View key={role} style={styles.item}>
            <AppButton
              label={role.charAt(0).toUpperCase() + role.slice(1)}
              variant={role === currentRole ? 'secondary' : 'ghost'}
              onPress={() => switchRole(role)}
            />
          </View>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    marginBottom: 12,
  },
  label: {
    color: colors.slate,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.4,
    marginBottom: 8,
    textTransform: 'uppercase',
  },
  row: {
    flexDirection: 'row',
    gap: 8,
  },
  item: {
    flex: 1,
  },
});
