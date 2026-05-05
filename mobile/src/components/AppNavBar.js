import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useAppContext } from '../context/AppContext';
import { colors } from '../theme/colors';

const routeByRole = {
  parent: [
    { key: 'home', label: 'Home', route: 'ParentDashboard', mark: 'H' },
    { key: 'students', label: 'Students', route: 'Students', mark: 'S' },
    { key: 'alerts', label: 'Alerts', route: 'Notifications', mark: 'A' },
    { key: 'chat', label: 'Chat', route: 'Chat', mark: 'C' },
    { key: 'profile', label: 'Profile', route: 'Profile', mark: 'P' },
  ],
  driver: [
    { key: 'home', label: 'Home', route: 'DriverDashboard', mark: 'H' },
    { key: 'trips', label: 'Trips', route: 'DriverTrips', mark: 'T' },
    { key: 'alerts', label: 'Alerts', route: 'Notifications', mark: 'A' },
    { key: 'chat', label: 'Chat', route: 'Chat', mark: 'C' },
    { key: 'profile', label: 'Profile', route: 'Profile', mark: 'P' },
  ],
  student: [
    { key: 'home', label: 'Home', route: 'StudentDashboard', mark: 'H' },
    { key: 'map', label: 'Map', route: 'ActiveRideMap', mark: 'M' },
    { key: 'alerts', label: 'Alerts', route: 'Notifications', mark: 'A' },
    { key: 'help', label: 'Help', route: 'Chat', mark: '!' },
    { key: 'profile', label: 'Profile', route: 'Profile', mark: 'P' },
  ],
};

export default function AppNavBar({ navigation, active }) {
  const { currentRole } = useAppContext();
  const items = routeByRole[currentRole] || routeByRole.parent;

  return (
    <View style={styles.shell}>
      <View style={styles.bar}>
        {items.map((item) => {
          const isActive = item.key === active;

          return (
            <Pressable
              key={item.key}
              accessibilityRole="button"
              accessibilityState={{ selected: isActive }}
              onPress={() => navigation.navigate(item.route)}
              style={({ pressed }) => [styles.item, isActive && styles.activeItem, pressed && styles.pressed]}
            >
              <View style={[styles.mark, isActive && styles.activeMark]}>
                <Text style={[styles.markText, isActive && styles.activeMarkText]}>{item.mark}</Text>
              </View>
              <Text numberOfLines={1} style={[styles.label, isActive && styles.activeLabel]}>
                {item.label}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  shell: {
    backgroundColor: colors.paper,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    paddingHorizontal: 14,
    paddingTop: 10,
    paddingBottom: 12,
  },
  bar: {
    minHeight: 64,
    borderRadius: 22,
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: '#ececec',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 6,
  },
  item: {
    flex: 1,
    minHeight: 54,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 16,
    paddingHorizontal: 3,
  },
  activeItem: {
    backgroundColor: colors.sky,
  },
  pressed: {
    opacity: 0.78,
  },
  mark: {
    width: 24,
    height: 24,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.sand,
    marginBottom: 4,
  },
  activeMark: {
    backgroundColor: colors.accent,
  },
  markText: {
    color: colors.slate,
    fontSize: 11,
    fontWeight: '900',
  },
  activeMarkText: {
    color: colors.white,
  },
  label: {
    color: colors.slate,
    fontSize: 11,
    fontWeight: '700',
  },
  activeLabel: {
    color: colors.ink,
  },
});
