import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import AppButton from './AppButton';
import { useAppShell } from '../navigation/AppShellContext';
import { colors } from '../theme/colors';

const roles = ['parent', 'driver', 'student'];

export default function RoleSimulator({ currentRole, loginAsRole }) {
  const { switchRole } = useAppShell();

  const handleSwitchRole = (role) => {
    if (switchRole) {
      switchRole(role);
      return;
    }

    loginAsRole(role);
  };

  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>Static simulator</Text>
      <View style={styles.row}>
        {roles.map((role) => (
          <View key={role} style={styles.item}>
            <AppButton
              label={role.charAt(0).toUpperCase() + role.slice(1)}
              variant={role === currentRole ? 'secondary' : 'ghost'}
              onPress={() => handleSwitchRole(role)}
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
