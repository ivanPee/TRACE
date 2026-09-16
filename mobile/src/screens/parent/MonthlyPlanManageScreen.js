import React, { useEffect, useMemo, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import AddressPinPicker from '../../components/AddressPinPicker';
import AppButton from '../../components/AppButton';
import AppNavBar from '../../components/AppNavBar';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';
import { colors } from '../../theme/colors';

const pad = (value) => String(value).padStart(2, '0');
const money = (value) => `PHP ${Number(value || 0).toFixed(2)}`;
const tripTypeOptions = [
  { value: 'one_way', label: 'One way', icon: 'arrow-right' },
  { value: 'round_trip', label: 'Round trip', icon: 'exchange-alt' },
];

const toMinutes = (time) => {
  const [hour, minute] = String(time || '00:00').split(':').map(Number);
  return (Number.isFinite(hour) ? hour : 0) * 60 + (Number.isFinite(minute) ? minute : 0);
};

const fromMinutes = (minutes) => {
  const clamped = Math.max(0, Math.min(23 * 60 + 45, minutes));
  return `${pad(Math.floor(clamped / 60))}:${pad(clamped % 60)}`;
};

const shiftTime = (time, minutes) => fromMinutes(toMinutes(time) + minutes);

function TimestampButton({ icon, label, onPress }) {
  return (
    <Pressable style={({ pressed }) => [styles.timestampButton, pressed && styles.pressed]} onPress={onPress}>
      {icon ? <FontAwesome5 name={icon} size={12} solid color={colors.ink} /> : null}
      <Text style={styles.timestampButtonText}>{label}</Text>
    </Pressable>
  );
}

function TimeField({ label, value, onChange, presets }) {
  return (
    <View style={styles.timestampField}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <View style={styles.timestampControl}>
        <TimestampButton icon="minus" label="15m" onPress={() => onChange(shiftTime(value, -15))} />
        <View style={styles.timestampValue}>
          <Text style={styles.timestampMain}>{value}</Text>
          <Text style={styles.timestampMeta}>Daily route</Text>
        </View>
        <TimestampButton icon="plus" label="15m" onPress={() => onChange(shiftTime(value, 15))} />
      </View>
      <View style={styles.quickRow}>
        {presets.map((preset) => (
          <TimestampButton key={preset} label={preset} onPress={() => onChange(preset)} />
        ))}
      </View>
    </View>
  );
}

const planToDraftRoutes = (plan) => (plan?.routes || []).map((route) => ({
  routeId: route.routeId || route.id,
  destinationAddress: route.destinationAddress || '',
  destinationLatitude: route.destinationLatitude,
  destinationLongitude: route.destinationLongitude,
  children: (route.children || []).map((child) => ({
    studentId: child.studentId,
    studentName: child.studentName,
    schoolName: child.schoolName,
    pickupAddress: child.pickupAddress || '',
    pickupLatitude: child.pickupLatitude,
    pickupLongitude: child.pickupLongitude,
  })),
}));

export default function MonthlyPlanManageScreen({ navigation }) {
  const { monthlyPlans, refreshDashboard, updateMonthlyPlan, cancelMonthlyPlan } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [selectedPlanId, setSelectedPlanId] = useState(null);
  const selectedPlan = useMemo(
    () => monthlyPlans.find((plan) => String(plan.id) === String(selectedPlanId)) || monthlyPlans[0],
    [monthlyPlans, selectedPlanId]
  );
  const [pickupTime, setPickupTime] = useState('07:00');
  const [dropoffTime, setDropoffTime] = useState('07:45');
  const [monthlyTripType, setMonthlyTripType] = useState('one_way');
  const [draftRoutes, setDraftRoutes] = useState([]);

  useEffect(() => {
    if (!selectedPlanId && monthlyPlans[0]) {
      setSelectedPlanId(monthlyPlans[0].id);
    }
  }, [monthlyPlans, selectedPlanId]);

  useEffect(() => {
    if (!selectedPlan) {
      return;
    }

    setPickupTime(selectedPlan.pickupTime || '07:00');
    setDropoffTime(selectedPlan.dropoffTime || '07:45');
    setMonthlyTripType(selectedPlan.tripType || 'one_way');
    setDraftRoutes(planToDraftRoutes(selectedPlan));
  }, [selectedPlan]);

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  const updateRoute = (routeId, patch) => {
    setDraftRoutes((current) => current.map((route) => (
      String(route.routeId) === String(routeId) ? { ...route, ...patch } : route
    )));
  };

  const updateChild = (routeId, studentId, patch) => {
    setDraftRoutes((current) => current.map((route) => {
      if (String(route.routeId) !== String(routeId)) {
        return route;
      }

      return {
        ...route,
        children: route.children.map((child) => (
          String(child.studentId) === String(studentId) ? { ...child, ...patch } : child
        )),
      };
    }));
  };

  const routesComplete = draftRoutes.every((route) => (
    String(route.destinationAddress || '').trim()
    && Number.isFinite(Number(route.destinationLatitude))
    && Number.isFinite(Number(route.destinationLongitude))
    && route.children.every((child) => (
      String(child.pickupAddress || '').trim()
      && Number.isFinite(Number(child.pickupLatitude))
      && Number.isFinite(Number(child.pickupLongitude))
    ))
  ));

  const handleSave = async () => {
    if (!selectedPlan) {
      return;
    }

    if (toMinutes(dropoffTime) <= toMinutes(pickupTime)) {
      Alert.alert('Monthly plan', 'Set a drop-off time later than the pickup time.');
      return;
    }

    if (!routesComplete) {
      Alert.alert('Monthly plan', 'Complete every pickup and drop-off pin before saving.');
      return;
    }

    setSaving(true);
    try {
      await updateMonthlyPlan(selectedPlan.id, {
        pickupTime,
        dropoffTime,
        monthlyTripType,
        routes: draftRoutes,
      });
      Alert.alert('Monthly plan', 'Plan changes were saved.');
    } catch (error) {
      Alert.alert('Monthly plan', error.message || 'Unable to update the monthly plan.');
    } finally {
      setSaving(false);
    }
  };

  const handleCancelPlan = () => {
    if (!selectedPlan) {
      return;
    }

    Alert.alert(
      'Cancel monthly plan',
      'Remaining unstarted daily transports for this subscription will be cancelled.',
      [
        { text: 'Keep Plan', style: 'cancel' },
        {
          text: 'Cancel Plan',
          style: 'destructive',
          onPress: async () => {
            setSaving(true);
            try {
              await cancelMonthlyPlan(selectedPlan.id);
              Alert.alert('Monthly plan', 'Subscription cancelled.');
            } catch (error) {
              Alert.alert('Monthly plan', error.message || 'Unable to cancel the monthly plan.');
            } finally {
              setSaving(false);
            }
          },
        },
      ]
    );
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="plans" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="Monthly Plan"
        title="Manage active transport subscriptions."
        subtitle="Plan changes update remaining daily routes, driver boards, and live trip context."
      />

      {!monthlyPlans.length ? (
        <SectionCard title="No active monthly plan" icon="calendar-plus">
          <Text style={styles.bodyText}>Create a paid monthly plan to open daily child transport routes.</Text>
          <AppButton icon="calendar-plus" label="Create Monthly Plan" onPress={() => navigation.navigate('BookRide')} />
        </SectionCard>
      ) : (
        <>
          {monthlyPlans.length > 1 ? (
            <View style={styles.planTabs}>
              {monthlyPlans.map((plan) => {
                const active = String(plan.id) === String(selectedPlan?.id);
                return (
                  <Pressable key={plan.id} style={[styles.planTab, active && styles.planTabActive]} onPress={() => setSelectedPlanId(plan.id)}>
                    <Text style={[styles.planTabText, active && styles.planTabTextActive]}>{plan.monthLabel}</Text>
                  </Pressable>
                );
              })}
            </View>
          ) : null}

          <SectionCard title={selectedPlan?.monthLabel || 'Monthly plan'} subtitle={`${selectedPlan?.childCount || 0} child${selectedPlan?.childCount === 1 ? '' : 'ren'} / ${selectedPlan?.routeCount || 0} route${selectedPlan?.routeCount === 1 ? '' : 's'}`} icon="calendar-check">
            <Pill label={selectedPlan?.paymentStatus === 'paid' ? 'Paid subscription' : selectedPlan?.status || 'Active'} tone="success" />
            <InfoRow icon="route" label="Trip type" value={monthlyTripType === 'round_trip' ? 'Round trip' : 'One way'} />
            <InfoRow icon="clock" label="Daily window" value={monthlyTripType === 'round_trip' ? `${pickupTime} to school, ${dropoffTime} return home` : `${pickupTime} pickup, ${dropoffTime} drop-off`} />
            <InfoRow icon="calendar-day" label="Service days" value={`${selectedPlan?.serviceDays || 0}`} />
            <InfoRow icon="wallet" label="Monthly total" value={money(selectedPlan?.monthlyAmount)} />
            <InfoRow icon="undo" label="No-class refund" value={`${money(selectedPlan?.refundPerNoClassDay)} per cancelled day`} />
            <AppButton icon="plus" label="Create Another Plan" variant="ghost" onPress={() => navigation.navigate('BookRide')} />
          </SectionCard>

          <SectionCard title="Times" icon="clock">
            <Text style={styles.fieldLabel}>Trip Type</Text>
            <View style={styles.tripTypeRow}>
              {tripTypeOptions.map((option) => {
                const selected = monthlyTripType === option.value;

                return (
                  <Pressable key={option.value} style={[styles.tripTypeOption, selected && styles.tripTypeOptionSelected]} onPress={() => setMonthlyTripType(option.value)}>
                    <FontAwesome5 name={option.icon} size={13} solid color={selected ? colors.white : colors.ink} />
                    <Text style={[styles.tripTypeText, selected && styles.tripTypeTextSelected]}>{option.label}</Text>
                  </Pressable>
                );
              })}
            </View>
            <TimeField label="Pickup Time" value={pickupTime} onChange={setPickupTime} presets={['06:30', '07:00', '07:30']} />
            <TimeField label={monthlyTripType === 'round_trip' ? 'Return Pickup Time' : 'Drop-off Time'} value={dropoffTime} onChange={setDropoffTime} presets={monthlyTripType === 'round_trip' ? ['15:30', '16:00', '16:30'] : ['07:30', '07:45', '08:00']} />
          </SectionCard>

          {draftRoutes.map((route, routeIndex) => (
            <SectionCard key={route.routeId || routeIndex} title={`Route ${routeIndex + 1}`} subtitle={`${route.children.length} child${route.children.length === 1 ? '' : 'ren'}`} icon="route">
              <AddressPinPicker
                label="Drop-off Location"
                value={route.destinationAddress}
                latitude={route.destinationLatitude}
                longitude={route.destinationLongitude}
                onChange={({ address, latitude, longitude }) => updateRoute(route.routeId, {
                  destinationAddress: address,
                  destinationLatitude: latitude,
                  destinationLongitude: longitude,
                })}
              />
              {route.children.map((child) => (
                <View key={child.studentId} style={styles.childBlock}>
                  <Text style={styles.childName}>{child.studentName}</Text>
                  <Text style={styles.childMeta}>{child.schoolName || route.destinationAddress}</Text>
                  <AddressPinPicker
                    label="Pickup Location"
                    value={child.pickupAddress}
                    latitude={child.pickupLatitude}
                    longitude={child.pickupLongitude}
                    onChange={({ address, latitude, longitude }) => updateChild(route.routeId, child.studentId, {
                      pickupAddress: address,
                      pickupLatitude: latitude,
                      pickupLongitude: longitude,
                    })}
                  />
                </View>
              ))}
            </SectionCard>
          ))}

          <SectionCard title="Subscription" icon="receipt">
            {!routesComplete ? <Text style={styles.warningText}>Complete every pickup and drop-off pin before saving.</Text> : null}
            <AppButton icon="save" label={saving ? 'Saving Plan...' : 'Save Plan Changes'} disabled={saving} onPress={handleSave} />
            <AppButton icon="ban" label="Cancel Subscription" variant="ghost" disabled={saving} onPress={handleCancelPlan} />
          </SectionCard>
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  bodyText: {
    color: colors.deep,
    lineHeight: 20,
    marginBottom: 12,
  },
  planTabs: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 12,
  },
  planTab: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  planTabActive: {
    backgroundColor: colors.accentSoft,
    borderColor: colors.accent,
  },
  planTabText: {
    color: colors.deep,
    fontSize: 13,
    fontWeight: '800',
  },
  planTabTextActive: {
    color: colors.ink,
  },
  fieldLabel: {
    color: colors.ink,
    fontSize: 14,
    fontWeight: '700',
    marginBottom: 6,
  },
  pressed: {
    opacity: 0.78,
  },
  timestampField: {
    marginBottom: 14,
  },
  timestampControl: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    minHeight: 64,
    padding: 8,
  },
  timestampButton: {
    alignItems: 'center',
    backgroundColor: colors.accentSoft,
    borderRadius: 8,
    flexDirection: 'row',
    gap: 6,
    justifyContent: 'center',
    minHeight: 38,
    minWidth: 66,
    paddingHorizontal: 10,
  },
  timestampButtonText: {
    color: colors.ink,
    fontSize: 12,
    fontWeight: '800',
  },
  timestampValue: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  timestampMain: {
    color: colors.ink,
    fontSize: 17,
    fontWeight: '900',
    textAlign: 'center',
  },
  timestampMeta: {
    color: colors.deep,
    fontSize: 11,
    marginTop: 3,
    textAlign: 'center',
  },
  quickRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 8,
  },
  tripTypeRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 14,
  },
  tripTypeOption: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    minHeight: 46,
    paddingHorizontal: 10,
  },
  tripTypeOptionSelected: {
    backgroundColor: colors.ink,
    borderColor: colors.ink,
  },
  tripTypeText: {
    color: colors.ink,
    fontSize: 13,
    fontWeight: '800',
  },
  tripTypeTextSelected: {
    color: colors.white,
  },
  childBlock: {
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    marginBottom: 12,
    padding: 12,
  },
  childName: {
    color: colors.ink,
    fontSize: 15,
    fontWeight: '800',
  },
  childMeta: {
    color: colors.slate,
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 10,
    marginTop: 2,
  },
  warningText: {
    color: colors.danger,
    fontWeight: '700',
    lineHeight: 20,
    marginBottom: 12,
  },
});
